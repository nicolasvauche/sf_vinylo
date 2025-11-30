<?php

namespace App\Service\Discogs\AddRecord;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class DiscogsHttpClient implements DiscogsClientInterface
{
    public function __construct(
        private HttpClientInterface $discogsClient,
        private string $userAgent,
        private string $token,
        private LoggerInterface $discogsLogger
    ) {
    }

    public function search(string $artistCanonical, string $recordCanonical): DiscogsSearchResult
    {
        $q = trim($artistCanonical.' '.$recordCanonical);

        $this->discogsLogger->info('discogs.search.start', ['q' => $q]);

        $masters = $this->getJson('/database/search', [
            'q' => $q,
            'type' => 'master',
            'per_page' => 5,
        ]);

        $releases = $this->getJson('/database/search', [
            'q' => $q,
            'type' => 'release',
            'per_page' => 5,
        ]);

        $candidates = [];
        $topMasterId = null;
        $topReleaseId = null;

        foreach (($masters['results'] ?? []) as $m) {
            $topMasterId ??= (string)($m['id'] ?? '');
            $candidates[] = $this->mapSearchResult($m, 'master');
        }
        foreach (($releases['results'] ?? []) as $r) {
            $topReleaseId ??= (string)($r['id'] ?? '');
            $candidates[] = $this->mapSearchResult($r, 'release');
        }

        if ($topMasterId) {
            $this->enrichImages($candidates, 'master', $topMasterId);
        }
        if ($topReleaseId) {
            $this->enrichImages($candidates, 'release', $topReleaseId);
        }

        foreach ($candidates as &$c) {
            if (!empty($c['covers'])) {
                $c['covers'] = array_slice($c['covers'], 0, 10);
            }
        }

        $this->discogsLogger->info('discogs.search.done', [
            'candidates' => count($candidates),
            'hasMaster' => (bool)$topMasterId,
            'hasRelease' => (bool)$topReleaseId,
        ]);

        return new DiscogsSearchResult($candidates, null);
    }

    private function getJson(string $path, array $query = []): array
    {
        $res = $this->discogsClient->request('GET', $path, [
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Authorization' => 'Discogs token='.$this->token,
            ],
            'query' => $query,
        ]);

        return $res->toArray(false);
    }

    private function mapSearchResult(array $r, string $type): array
    {
        $artistName = (string)($r['artist'] ?? ($r['title'] ?? ''));
        $title = (string)($r['title'] ?? '');
        if (str_contains($title, ' - ')) {
            [$an, $tt] = explode(' - ', $title, 2);
            if (!empty($an)) {
                $artistName = $an;
            }
            if (!empty($tt)) {
                $title = $tt;
            }
        }

        $covers = [];
        if (!empty($r['cover_image'])) {
            $covers[] = ['url' => (string)$r['cover_image'], 'source' => $type];
        }

        return [
            'artistName' => $artistName,
            'artistId' => isset($r['artist_id']) ? (string)$r['artist_id'] : null,
            'recordTitle' => $title,
            'masterId' => $type === 'master' ? (string)($r['id'] ?? '') : null,
            'releaseId' => $type === 'release' ? (string)($r['id'] ?? '') : null,
            'covers' => $covers,
            'years' => [],
            'countries' => [],
        ];
    }

    private function enrichImages(array &$candidates, string $type, string $id): void
    {
        $detail = $this->getJson($type === 'master' ? "/masters/$id" : "/releases/$id");

        $imgs = [];
        foreach (($detail['images'] ?? []) as $img) {
            if (!empty($img['uri'])) {
                $imgs[] = [
                    'url' => (string)$img['uri'],
                    'width' => $img['width'] ?? null,
                    'height' => $img['height'] ?? null,
                    'source' => $type,
                ];
            }
        }

        $years = [];
        if ($type === 'master') {
            if (!empty($detail['year'])) {
                $years[] = (int)$detail['year'];
            }
        } else {
            if (!empty($detail['year'])) {
                $years[] = (int)$detail['year'];
            }
            if (!empty($detail['released'])) {
                $y = substr((string)$detail['released'], 0, 4);
                if (ctype_digit($y)) {
                    $years[] = (int)$y;
                }
            }
        }

        $releaseCountry = null;
        if ($type === 'release' && !empty($detail['country']) && is_string($detail['country'])) {
            $releaseCountry = $detail['country'];
        }

        foreach ($candidates as &$c) {
            if (($type === 'master' && ($c['masterId'] ?? null) === $id)
                || ($type === 'release' && ($c['releaseId'] ?? null) === $id)) {
                if (!empty($imgs)) {
                    $c['covers'] = $imgs;
                }
                if (!empty($years)) {
                    $c['years'] = array_values(array_unique($years));
                }
                if ($releaseCountry) {
                    $c['countries'] ??= [];
                    $c['countries'][] = $releaseCountry;
                    $c['countries'] = array_values(array_unique(array_filter($c['countries'])));
                }
                break;
            }
        }
    }
}
