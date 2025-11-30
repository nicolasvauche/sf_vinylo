<?php

namespace App\Service\Vault\AddRecord\Resolver;

use App\Entity\Vault\Draft\EditionDraft;
use App\Service\Ai\AddRecord\AiClientInterface;
use App\Service\Discogs\AddRecord\DiscogsClientInterface;
use App\Service\Vault\AddRecord\Normalization\Canonicalizer;
use App\Service\Vault\AddRecord\Normalization\TitleCaser;
use Psr\Log\LoggerInterface;

final readonly class AddRecordResolver
{
    public function __construct(
        private DiscogsClientInterface $discogs,
        private AiClientInterface $ai,
        private Canonicalizer $canonicalizer,
        private TitleCaser $titleCaser,
        private LoggerInterface $discogsLogger,
        private LoggerInterface $aiLogger
    ) {
    }

    public function fetchDiscogs(EditionDraft $draft): array
    {
        $a = $draft->getArtistCanonical();
        $r = $draft->getRecordCanonical();

        $this->discogsLogger->info('discogs.request', ['draftId' => $draft->getId(), 'artist' => $a, 'record' => $r]);

        $result = $this->discogs->search($a, $r);

        $kept = [
            'candidates' => $result->candidates,
            'chosen' => $result->chosen,
            'error' => null,
        ];

        $this->discogsLogger->info('discogs.response', [
            'draftId' => $draft->getId(),
            'kept_count' => count($kept['candidates']),
            'chosen' => $kept['chosen'],
        ]);

        return $kept;
    }

    public function callAi(EditionDraft $draft, array $discogs): array
    {
        $input = [
            'artistRawDisplay' => $draft->getInput()['artistRaw'] ?? '',
            'recordRawDisplay' => $draft->getInput()['recordRaw'] ?? '',
            'artistCanonical' => $draft->getArtistCanonical(),
            'recordCanonical' => $draft->getRecordCanonical(),
        ];
        $candidates = $discogs['candidates'] ?? [];

        $this->aiLogger->info('ai.request', ['draftId' => $draft->getId(), 'candidates_count' => count($candidates)]);
        $out = $this->ai->enrich($input, $candidates);
        $this->aiLogger->info('ai.response', [
            'draftId' => $draft->getId(),
            'artist.displayName' => $out['artist']['displayName'] ?? null,
            'record.displayTitle' => $out['record']['displayTitle'] ?? null,
            'yearOriginal' => $out['record']['yearOriginal'] ?? null,
        ]);

        return $out + ['error' => null];
    }

    public function buildResolved(EditionDraft $draft, array $discogs, array $ai): array
    {
        $chosen = $discogs['chosen'] ?? null;
        $candidate = null;
        if ($chosen) {
            foreach ($discogs['candidates'] ?? [] as $c) {
                if (($chosen['type'] ?? null) === 'master' && ($c['masterId'] ?? null) === ($chosen['id'] ?? null)) {
                    $candidate = $c;
                    break;
                }
                if (($chosen['type'] ?? null) === 'release' && ($c['releaseId'] ?? null) === ($chosen['id'] ?? null)) {
                    $candidate = $c;
                    break;
                }
            }
        }
        $candidate ??= ($discogs['candidates'][0] ?? null);

        // Artist
        $artistDisplay = $ai['artist']['displayName'] ?? ($candidate['artistName'] ?? $draft->getInput(
        )['artistRaw'] ?? '');
        $artistName = $this->titleCaser->titleCase((string)$artistDisplay);
        $artistCanonical = $this->canonicalizer->canonicalize($artistName);

        $cc = $ai['artist']['countryCode'] ?? 'XX';
        $cc = (is_string($cc) && preg_match('/^[A-Z]{2}$/', $cc)) ? $cc : 'XX';
        $cn = ($cc === 'XX') ? null : ($ai['artist']['countryName'] ?? null);
        $discogsArtistId = $candidate['artistId'] ?? null;

        // Record
        $recordDisplay = $ai['record']['displayTitle'] ?? ($candidate['recordTitle'] ?? $draft->getInput(
        )['recordRaw'] ?? '');
        $recordTitle = $this->titleCaser->titleCase((string)$recordDisplay);
        $recordCanonical = $this->canonicalizer->canonicalize($recordTitle);

        // Year
        $yearAi = $ai['record']['yearOriginal'] ?? null;
        $year = $this->normalizeYear($yearAi);
        if ($year === null) {
            $years = $candidate['years'] ?? [];
            sort($years);
            $year = $this->normalizeYear($years[0] ?? null) ?? '0000';
        }

        // Discogs IDs
        $discogsMasterId = $candidate['masterId'] ?? null;
        $discogsReleaseId = $candidate['releaseId'] ?? null;

        // Covers (≤10), coverDefault
        $covers = array_slice($candidate['covers'] ?? [], 0, 10);
        $defaultIndex = 0;
        foreach ($covers as $i => $c) {
            if (($c['source'] ?? null) === 'master') {
                $defaultIndex = $i;
                break;
            }
        }

        return [
            'artist' => [
                'name' => $artistName,
                'nameCanonical' => $artistCanonical,
                'countryCode' => $cc,
                'countryName' => $cn,
                'discogsArtistId' => $discogsArtistId,
            ],
            'record' => [
                'title' => $recordTitle,
                'titleCanonical' => $recordCanonical,
                'yearOriginal' => $year,
                'discogsMasterId' => $discogsMasterId,
                'discogsReleaseId' => $discogsReleaseId,
            ],
            'covers' => $covers,
            'coverDefaultIndex' => empty($covers) ? 0 : $defaultIndex,
        ];
    }

    private function normalizeYear($y): ?string
    {
        if (!is_string($y) && !is_int($y)) {
            return null;
        }
        $y = (string)$y;
        if (!preg_match('/^\d{4}$/', $y)) {
            return null;
        }
        $i = (int)$y;
        $max = (int)(new \DateTimeImmutable())->format('Y') + 1;
        if ($i < 1900 || $i > $max) {
            return null;
        }

        return $y;
    }
}
