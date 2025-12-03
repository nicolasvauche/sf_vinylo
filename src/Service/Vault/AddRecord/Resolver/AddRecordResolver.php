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
    /** @var string[] */
    private const ALLOWED_FORMATS = ['33T', '45T', 'Maxi45T', '78T', 'Mixte', 'Inconnu'];

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

        $this->discogsLogger->info('discogs.request', [
            'draftId' => $draft->getId(),
            'artist' => $a,
            'record' => $r,
        ]);

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

        $this->aiLogger->info('ai.request', [
            'draftId' => $draft->getId(),
            'candidates_count' => count($candidates),
            'payload' => ['input' => $input],
        ]);

        $out = $this->ai->enrich($input, $candidates);

        $this->aiLogger->info('ai.response', [
            'draftId' => $draft->getId(),
            'artist.displayName' => $out['artist']['displayName'] ?? null,
            'artist.countryCode' => $out['artist']['countryCode'] ?? null,
            'record.displayTitle' => $out['record']['displayTitle'] ?? null,
            'record.yearOriginal' => $out['record']['yearOriginal'] ?? null,
            'record.format' => $out['record']['format'] ?? null,
        ]);

        return $out + ['error' => null];
    }

    public function buildResolved(EditionDraft $draft, array $discogs, array $ai): array
    {
        $chosen = $discogs['chosen'] ?? null;
        $candidates = $discogs['candidates'] ?? [];

        $candidate = null;

        if ($chosen) {
            foreach ($candidates as $c) {
                if (
                    ($chosen['type'] === 'master' && ($c['masterId'] ?? null) === ($chosen['id'] ?? null)) ||
                    ($chosen['type'] === 'release' && ($c['releaseId'] ?? null) === ($chosen['id'] ?? null))
                ) {
                    $candidate = $c;
                    break;
                }
            }
        }
        $candidate ??= ($candidates[0] ?? []);

        // -------------------------
        // ARTIST
        // -------------------------
        $artistDisplay = $ai['artist']['displayName']
            ?? $candidate['artistName']
            ?? ($draft->getInput()['artistRaw'] ?? '');

        $artistName = $this->titleCaser->titleCase((string)$artistDisplay);
        $artistCanonical = $this->canonicalizer->canonicalize($artistName);

        $cc = $ai['artist']['countryCode'] ?? 'XX';
        $cc = preg_match('/^[A-Z]{2}$/', (string)$cc) ? (string)$cc : 'XX';

        $cn = ($cc === 'XX') ? null : ($ai['artist']['countryName'] ?? null);

        $discogsArtistId = $candidate['artistId'] ?? null;

        // -------------------------
        // RECORD
        // -------------------------
        $recordDisplay = $ai['record']['displayTitle']
            ?? $candidate['recordTitle']
            ?? ($draft->getInput()['recordRaw'] ?? '');

        $recordTitle = $this->titleCaser->titleCase((string)$recordDisplay);
        $recordCanonical = $this->canonicalizer->canonicalize($recordTitle);

        // YEAR (AI → candidate → 0000)
        $yearAi = $ai['record']['yearOriginal'] ?? null;
        $year = $this->normalizeYear($yearAi);

        if ($year === null) {
            $years = $candidate['years'] ?? [];
            sort($years);
            $year = $this->normalizeYear($years[0] ?? null) ?? '0000';
        }

        // IDs
        $discogsMasterId = $candidate['masterId'] ?? null;
        $discogsReleaseId = $candidate['releaseId'] ?? null;

        // -------------------------
        // FORMAT (IA)
        // -------------------------
        $aiFormatRaw = $ai['record']['format'] ?? null;
        $format = $this->normalizeAiFormat($aiFormatRaw);

        $this->aiLogger->info('ai.record.format.normalized', [
            'draftId' => $draft->getId(),
            'format_raw' => $aiFormatRaw,
            'format_final' => $format,
        ]);

        // -------------------------
        // COVERS
        // -------------------------
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
                'format' => $format,     // <-- ajouté
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

        return ($i >= 1900 && $i <= $max) ? $y : null;
    }

    private function normalizeAiFormat(mixed $fmt): string
    {
        if (!is_string($fmt) || $fmt === '') {
            return 'Inconnu';
        }
        $fmt = trim($fmt);

        if (in_array($fmt, self::ALLOWED_FORMATS, true)) {
            return $fmt;
        }

        $l = mb_strtolower($fmt, 'UTF-8');
        if ($l === '33' || $l === '33t' || str_contains($l, 'lp')) {
            return '33T';
        }
        if ($l === '45' || $l === '45t' || str_contains($l, '7"')) {
            return '45T';
        }
        if (str_contains($l, '12"') || str_contains($l, 'maxi')) {
            return 'Maxi45T';
        }
        if (str_contains($l, '78')) {
            return '78T';
        }
        if (str_contains($l, 'mix')) {
            return 'Mixte';
        }

        return 'Inconnu';
    }
}
