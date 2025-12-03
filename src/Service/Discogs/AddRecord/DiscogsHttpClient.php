<?php

namespace App\Service\Discogs\AddRecord;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class DiscogsHttpClient implements DiscogsClientInterface
{
    private const MAX_RETRIES = 4;
    private const BASE_BACKOFF_MS = 400;

    private const SEARCH_PER_PAGE = 50;
    private const DETAIL_RELEASES_LIMIT = 6;
    private const MASTER_VERSIONS_PER_PAGE = 50;
    private const MASTER_TOP_COUNT = 2;

    public function __construct(
        private HttpClientInterface $discogsClient,
        private string $userAgent,
        private string $token,
        private LoggerInterface $discogsLogger
    ) {
    }

    public function search(string $artistCanonical, string $recordCanonical): DiscogsSearchResult
    {
        $norm = static function (?string $s): string {
            $s ??= '';
            $s = str_replace('+', ' ', $s);
            $s = preg_replace('/\s+/u', ' ', $s);

            return trim($s);
        };
        $nkey = static function (string $s): string {
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
            $s = preg_replace('/\s+/u', ' ', $s);

            return trim($s);
        };

        $artistCanonical = $norm($artistCanonical);
        $recordCanonical = $norm($recordCanonical);
        $q = $norm(trim($artistCanonical.' '.$recordCanonical));

        $this->discogsLogger->info('discogs.search.start', [
            'artist' => $artistCanonical,
            'title' => $recordCanonical,
            'q' => $q,
        ]);

        if ($artistCanonical === '' && $recordCanonical === '' && $q === '') {
            return new DiscogsSearchResult([], null);
        }

        $search = $this->requestJson('/database/search', [
            'format' => 'Vinyl',
            'type' => 'release',
            'status' => 'official',
            'artist' => $artistCanonical ?: null,
            'title' => $recordCanonical ?: null,
            'sort' => 'year',
            'sort_order' => 'asc',
            'page' => 1,
            'per_page' => self::SEARCH_PER_PAGE,
        ]);
        $results = $search['results'] ?? [];

        if (empty($results) && $q !== '') {
            $this->discogsLogger->info('discogs.search.fallback_q', ['q' => $q]);
            $search = $this->requestJson('/database/search', [
                'q' => $q,
                'format' => 'Vinyl',
                'type' => 'release',
                'status' => 'official',
                'sort' => 'year',
                'sort_order' => 'asc',
                'page' => 1,
                'per_page' => self::SEARCH_PER_PAGE,
            ]);
            $results = $search['results'] ?? [];
        }

        $filtered = array_values(array_filter($results, function ($r) {
            if (!isset($r['cover_image']) || !is_string($r['cover_image'])) {
                return false;
            }
            if (str_contains((string)$r['cover_image'], 'spacer.gif')) {
                return false;
            }
            $country = (string)($r['country'] ?? '');
            if ($country === '' || strtolower($country) === 'unknown') {
                return false;
            }

            return true;
        }));

        $titleKey = $nkey($recordCanonical);
        $artistKey = $nkey($artistCanonical);
        $scored = [];
        $masterFreq = [];

        foreach ($filtered as $r) {
            $score = 0;

            $titleRaw = (string)($r['title'] ?? '');
            $artistParsed = $titleRaw;
            $albumParsed = $titleRaw;
            if (str_contains($titleRaw, ' - ')) {
                [$a, $t] = explode(' - ', $titleRaw, 2);
                $artistParsed = trim($a);
                $albumParsed = trim($t);
            }

            $ak = $nkey($artistParsed);
            $tk = $nkey($albumParsed);

            if ($artistKey !== '' && $ak === $artistKey) {
                $score += 100;
            } elseif ($artistKey !== '' && str_starts_with($ak, $artistKey)) {
                $score += 60;
            } elseif ($artistKey !== '' && str_contains($ak, $artistKey)) {
                $score += 30;
            }

            if ($titleKey !== '' && $tk === $titleKey) {
                $score += 100;
            } elseif ($titleKey !== '' && str_starts_with($tk, $titleKey)) {
                $score += 60;
            } elseif ($titleKey !== '' && str_contains($tk, $titleKey)) {
                $score += 30;
            }

            if (isset($r['year']) && is_numeric($r['year'])) {
                $score += 5;
            }

            $mid = $r['master_id'] ?? null;
            if ($mid) {
                $masterFreq[(string)$mid] = ($masterFreq[(string)$mid] ?? 0) + 1;
            }

            $scored[] = [$score, $r];
        }
        usort($scored, static fn($a, $b) => $b[0] <=> $a[0]);

        $candidates = [];
        $releaseIdsForDetail = [];
        foreach ($scored as [$s, $r]) {
            $id = (string)($r['id'] ?? '');
            if ($id !== '') {
                $releaseIdsForDetail[] = $id;
            }
            $candidates[] = $this->mapSearchResult($r, 'release');
        }

        $topMasters = array_slice(
            array_keys(array_reverse(array_sort($masterFreq))),
            0,
            self::MASTER_TOP_COUNT
        );

        foreach ($topMasters as $mid) {
            $versions = $this->requestJson("/masters/$mid/versions", [
                'per_page' => self::MASTER_VERSIONS_PER_PAGE,
                'page' => 1,
            ]);
            $vers = $versions['versions'] ?? [];
            if (!is_array($vers) || empty($vers)) {
                continue;
            }

            usort($vers, static function ($a, $b) {
                $ha = (int)($a['stats']['community']['inCollection'] ?? 0);
                $hb = (int)($b['stats']['community']['inCollection'] ?? 0);

                return $hb <=> $ha;
            });

            $top = array_slice($vers, 0, 3);
            foreach ($top as $v) {
                $rid = (string)($v['id'] ?? '');
                if ($rid !== '') {
                    $releaseIdsForDetail[] = $rid;
                }
            }
        }

        $releaseIdsForDetail = array_slice(
            array_values(array_unique($releaseIdsForDetail)),
            0,
            self::DETAIL_RELEASES_LIMIT
        );

        foreach ($releaseIdsForDetail as $rid) {
            $detail = $this->requestJson("/releases/$rid");
            $this->enrichFromDetail($candidates, 'release', (string)$rid, $detail);
        }

        $coverFreq = [];
        foreach ($filtered as $r) {
            $img = (string)($r['cover_image'] ?? '');
            if ($img !== '' && !str_contains($img, 'spacer.gif')) {
                $coverFreq[$img] = ($coverFreq[$img] ?? 0) + 1;
            }
        }

        foreach ($candidates as &$c) {
            $covers = $c['covers'] ?? [];
            foreach ($covers as &$cv) {
                $cv['_freq'] = $coverFreq[$cv['url']] ?? 0;
            }
            $c['covers'] = $this->rankAndLimitCovers($covers);
        }

        foreach ($candidates as &$c) {
            if (!empty($c['covers'])) {
                $c['covers'] = array_slice($c['covers'], 0, 10);
            }
        }

        $this->discogsLogger->info('discogs.search.done', [
            'candidates' => count($candidates),
            'enriched_releases' => count($releaseIdsForDetail),
            'top_masters' => $topMasters,
        ]);

        return new DiscogsSearchResult($candidates, null);
    }

    private function requestJson(string $path, array $query = []): array
    {
        $attempt = 0;
        $backoffMs = self::BASE_BACKOFF_MS;

        while (true) {
            $attempt++;
            try {
                $response = $this->discogsClient->request('GET', $path, [
                    'headers' => [
                        'User-Agent' => $this->userAgent,
                        'Authorization' => 'Discogs token='.$this->token,
                        'Accept' => 'application/json',
                    ],
                    'query' => array_filter($query, static fn($v) => $v !== null),
                    'timeout' => 15.0,
                    'max_duration' => 30.0,
                ]);

                $status = $response->getStatusCode();
                $this->logRateHeaders($response, $path, $query);

                if ($status === 429) {
                    $retryAfter = $this->retryAfterSeconds($response);
                    $this->discogsLogger->warning('discogs.http.429', [
                        'path' => $path,
                        'query' => $query,
                        'attempt' => $attempt,
                        'retry_after' => $retryAfter,
                    ]);
                    $this->sleepSeconds($retryAfter ?: ($backoffMs / 1000));
                    if ($attempt < self::MAX_RETRIES) {
                        $backoffMs *= 2;
                        continue;
                    }

                    return [];
                }

                if ($status >= 500 && $attempt < self::MAX_RETRIES) {
                    $this->discogsLogger->warning('discogs.http.5xx_retry', [
                        'status' => $status,
                        'path' => $path,
                        'query' => $query,
                        'attempt' => $attempt,
                    ]);
                    $this->sleepMs($backoffMs);
                    $backoffMs *= 2;
                    continue;
                }

                if ($status >= 400) {
                    $this->discogsLogger->warning('discogs.http.status', [
                        'status' => $status,
                        'path' => $path,
                        'query' => $query,
                    ]);

                    return [];
                }

                $raw = $response->getContent(false);
                $data = \json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                return \is_array($data) ? $data : [];
            } catch (\Throwable $e) {
                if ($attempt < self::MAX_RETRIES) {
                    $this->discogsLogger->warning('discogs.http.exception_retry', [
                        'error' => $e->getMessage(),
                        'path' => $path,
                        'query' => $query,
                        'attempt' => $attempt,
                    ]);
                    $this->sleepMs($backoffMs);
                    $backoffMs *= 2;
                    continue;
                }

                $this->discogsLogger->warning('discogs.http.exception_giveup', [
                    'error' => $e->getMessage(),
                    'path' => $path,
                    'query' => $query,
                    'attempt' => $attempt,
                ]);

                return [];
            }
        }
    }

    private function retryAfterSeconds(ResponseInterface $res): ?int
    {
        $retryHeader = $res->getHeaders(false)['retry-after'][0] ?? null;
        if ($retryHeader !== null) {
            if (ctype_digit($retryHeader)) {
                return (int)$retryHeader;
            }
            $ts = strtotime($retryHeader);
            if ($ts !== false) {
                $delta = $ts - time();

                return $delta > 0 ? $delta : 1;
            }
        }

        return null;
    }

    private function logRateHeaders(ResponseInterface $res, string $path, array $query): void
    {
        $h = $res->getHeaders(false);
        $remain = $h['x-discogs-ratelimit-remaining'][0] ?? null;
        $limit = $h['x-discogs-ratelimit'][0] ?? null;
        $used = $h['x-discogs-ratelimit-used'][0] ?? null;

        $this->discogsLogger->info('discogs.http.ratelimit', [
            'path' => $path,
            'query' => $query,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remain,
        ]);
    }

    private function sleepMs(int $ms): void
    {
        usleep(max(1, $ms) * 1000);
    }

    private function sleepSeconds(float $s): void
    {
        usleep((int)max(1, $s * 1000000));
    }

    private function mapSearchResult(array $r, string $type): array
    {
        $artistName = (string)($r['artist'] ?? ($r['title'] ?? ''));
        $title = (string)($r['title'] ?? '');

        if (str_contains($title, ' - ')) {
            [$an, $tt] = explode(' - ', $title, 2);
            if ($an !== '') {
                $artistName = $an;
            }
            if ($tt !== '') {
                $title = $tt;
            }
        }

        $covers = [];
        if (!empty($r['cover_image']) && !str_contains((string)$r['cover_image'], 'spacer.gif')) {
            $covers[] = [
                'url' => (string)$r['cover_image'],
                'width' => null,
                'height' => null,
                'source' => 'search',
                'kind' => 'search_thumb',
            ];
        }

        return [
            'artistName' => $artistName,
            'artistId' => isset($r['artist_id']) ? (string)$r['artist_id'] : null,
            'recordTitle' => $title,
            'masterId' => isset($r['master_id']) ? (string)$r['master_id'] : null,
            'releaseId' => $type === 'release' ? (string)($r['id'] ?? '') : null,
            'covers' => $covers,
            'years' => isset($r['year']) && is_numeric($r['year']) ? [(int)$r['year']] : [],
            'countries' => !empty($r['country']) ? [(string)$r['country']] : [],
        ];
    }

    private function enrichFromDetail(array &$candidates, string $type, string $id, array $detail): void
    {
        if ($type !== 'release') {
            return;
        }

        $imgs = [];
        foreach (($detail['images'] ?? []) as $img) {
            $uri = (string)($img['uri'] ?? '');
            if ($uri !== '' && !str_contains($uri, 'spacer.gif')) {
                $imgs[] = [
                    'url' => $uri,
                    'width' => $img['width'] ?? null,
                    'height' => $img['height'] ?? null,
                    'source' => 'release_detail',
                    'kind' => (string)($img['type'] ?? ''),
                ];
            }
        }

        $years = [];
        if (!empty($detail['year'])) {
            $years[] = (int)$detail['year'];
        }
        if (!empty($detail['released'])) {
            $y = substr((string)$detail['released'], 0, 4);
            if (ctype_digit($y)) {
                $years[] = (int)$y;
            }
        }

        $country = null;
        if (!empty($detail['country']) && is_string($detail['country'])) {
            $country = $detail['country'];
        }

        $artistId = $this->extractArtistId($detail);

        foreach ($candidates as &$c) {
            if (($c['releaseId'] ?? null) !== $id) {
                continue;
            }

            if ($imgs) {
                $merged = array_merge($c['covers'] ?? [], $imgs);
                $c['covers'] = $this->rankAndLimitCovers($merged);
            }

            if ($years) {
                $c['years'] = array_values(array_unique(array_merge($c['years'] ?? [], $years)));
            }
            if ($country) {
                $c['countries'] ??= [];
                $c['countries'][] = $country;
                $c['countries'] = array_values(array_unique(array_filter($c['countries'])));
            }
            if ($artistId && (($c['artistId'] ?? null) !== $artistId)) {
                $c['artistId'] = $artistId;
                $this->discogsLogger->info('discogs.release.artist_id_found', [
                    'id' => $id,
                    'artistId' => $artistId,
                ]);
            }
            break;
        }
    }

    private function extractArtistId(array $detail): ?string
    {
        $artists = $detail['artists'] ?? null;
        if (is_array($artists) && !empty($artists)) {
            foreach ($artists as $a) {
                if (isset($a['id']) && $a['id'] !== null && $a['id'] !== '') {
                    $sid = (string)$a['id'];
                    if ($sid !== '') {
                        return $sid;
                    }
                }
            }
        }

        return null;
    }

    private function rankAndLimitCovers(array $covers): array
    {
        $seen = [];
        $norm = function (string $url): string {
            $u = preg_replace('#^http:#', 'https:', $url);
            $u = (string)preg_replace('#\?.*$#', '', $u);

            return $u;
        };

        $unique = [];
        foreach ($covers as $c) {
            $url = isset($c['url']) ? (string)$c['url'] : '';
            if ($url === '') {
                continue;
            }
            $key = $norm($url);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $c['url'] = $key;
                $unique[] = $c;
            }
        }

        $scored = [];
        foreach ($unique as $c) {
            $scored[] = [$this->scoreCover($c), $c];
        }

        usort($scored, static fn($a, $b) => $b[0] <=> $a[0]);

        $out = [];
        foreach ($scored as [$score, $c]) {
            $out[] = $c;
            if (count($out) >= 10) {
                break;
            }
        }

        return $out;
    }

    private function scoreCover(array $c): int
    {
        $score = 0;

        $kind = strtolower((string)($c['kind'] ?? ''));
        $src = strtolower((string)($c['source'] ?? ''));
        $url = strtolower((string)($c['url'] ?? ''));
        $freq = (int)($c['_freq'] ?? 0);

        if ($kind === 'primary') {
            $score += 2000;
        }
        if (preg_match('#(front|cover|sleeve)#i', $url)) {
            $score += 200;
        }
        if ($src === 'release_detail') {
            $score += 120;
        }
        if ($src === 'search') {
            $score += 20;
        }
        $score += min(200, $freq * 25);

        if (preg_match(
                '#(label|side\s*[ab]|vinyl|record|disc|matrix|runout|obi|sticker|poster|insert|booklet)#i',
                $url
            ) && $kind !== 'primary') {
            $score -= 400;
        }
        if (str_contains($url, 'spacer.gif')) {
            $score -= 2000;
        }

        $w = (int)($c['width'] ?? 0);
        $h = (int)($c['height'] ?? 0);
        if ($w > 0 && $h > 0) {
            $score += (int)(min($w, $h) / 8);
        }

        return $score;
    }
}

if (!function_exists('array_sort')) {
    function array_sort(array $assoc): array
    {
        asort($assoc);

        return $assoc;
    }
}
