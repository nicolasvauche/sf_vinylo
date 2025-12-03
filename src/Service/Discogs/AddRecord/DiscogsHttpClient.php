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
        $masterIds = [];
        $releaseIds = [];

        // Map masters
        foreach (($masters['results'] ?? []) as $m) {
            $id = (string)($m['id'] ?? '');
            if ($id !== '') {
                $masterIds[] = $id;
            }
            $candidates[] = $this->mapSearchResult($m, 'master');
        }

        // Map releases
        foreach (($releases['results'] ?? []) as $r) {
            $id = (string)($r['id'] ?? '');
            if ($id !== '') {
                $releaseIds[] = $id;
            }
            $candidates[] = $this->mapSearchResult($r, 'release');
        }

        foreach (array_slice($masterIds, 0, 3) as $mid) {
            $this->enrichFromDetail($candidates, 'master', $mid);
        }
        foreach (array_slice($releaseIds, 0, 5) as $rid) {
            $this->enrichFromDetail($candidates, 'release', $rid);
        }

        foreach ($candidates as &$c) {
            if (!empty($c['covers'])) {
                $c['covers'] = array_slice($c['covers'], 0, 10);
            }
        }

        $this->discogsLogger->info('discogs.search.done', [
            'candidates' => count($candidates),
            'masters' => count($masterIds),
            'releases' => count($releaseIds),
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
            if ($an !== '') {
                $artistName = $an;
            }
            if ($tt !== '') {
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

    private function enrichFromDetail(array &$candidates, string $type, string $id): void
    {
        $detail = $this->getJson($type === 'master' ? "/masters/$id" : "/releases/$id");

        // IMAGES
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

        // YEARS
        $years = [];
        if (!empty($detail['year'])) {
            $years[] = (int)$detail['year'];
        }
        if ($type === 'release' && !empty($detail['released'])) {
            $y = substr((string)$detail['released'], 0, 4);
            if (ctype_digit($y)) {
                $years[] = (int)$y;
            }
        }

        // COUNTRY (release only)
        $releaseCountry = null;
        if ($type === 'release' && !empty($detail['country']) && is_string($detail['country'])) {
            $releaseCountry = $detail['country'];
        }

        // ARTIST ID
        $artistIdFromDetail = $this->extractArtistId($detail);

        foreach ($candidates as &$c) {
            $isTarget =
                ($type === 'master' && ($c['masterId'] ?? null) === $id)
                || ($type === 'release' && ($c['releaseId'] ?? null) === $id);

            if (!$isTarget) {
                continue;
            }

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
            if ($artistIdFromDetail) {
                if (($c['artistId'] ?? null) !== $artistIdFromDetail) {
                    $c['artistId'] = $artistIdFromDetail;
                    $this->discogsLogger->info(
                        $type === 'master' ? 'discogs.master.artist_id_found' : 'discogs.release.artist_id_found',
                        ['id' => $id, 'artistId' => $artistIdFromDetail]
                    );
                }
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
}
