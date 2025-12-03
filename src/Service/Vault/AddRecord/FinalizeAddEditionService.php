<?php

namespace App\Service\Vault\AddRecord;

use App\Dto\Vault\Collection\ValidateEditionDto;
use App\Entity\User\User;
use App\Entity\Vault\Catalog\Artist;
use App\Entity\Vault\Catalog\Record;
use App\Entity\Vault\Collection\Edition;
use App\Entity\Vault\Draft\EditionDraft;
use App\ValueObject\Vault\AddRecord\DraftStatus;
use App\ValueObject\Vault\RecordFormat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FinalizeAddEditionService
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire(param: 'app.uploads_directory')] private string $uploadsDir,
    ) {
    }

    public function finalize(EditionDraft $draft, ValidateEditionDto $data, User $owner): int
    {
        if ($draft->getOwner() !== $owner) {
            throw new \RuntimeException('Draft ownership mismatch');
        }
        if ($draft->getStatus() !== DraftStatus::READY) {
            throw new \RuntimeException('Draft not READY');
        }

        $resolved = $draft->getResolved() ?? [];

        $artistCanonical = (string)($resolved['artist']['nameCanonical'] ?? '');
        $recordCanonical = (string)($resolved['record']['titleCanonical'] ?? '');
        $yearOriginalCanonical = (string)($resolved['record']['yearOriginal'] ?? '0000');

        $artistName = (string)$data->artistName;
        $recordTitle = (string)$data->recordTitle;
        $yearOriginal = (string)($data->recordYear ?: $yearOriginalCanonical);

        $formatFromResolved = (string)($resolved['record']['format'] ?? '');
        $formatFromDto = (string)($data->recordFormat ?? '');
        $formatEnum = $this->resolveFormatEnum($formatFromResolved, $formatFromDto);

        $coverUrl = null;
        $covers = $data->covers ?? [];
        $coverIndex = (int)($data->coverDefaultIndex ?? 0);
        if (!empty($covers) && isset($covers[$coverIndex]['url']) && is_string($covers[$coverIndex]['url'])) {
            $coverUrl = $covers[$coverIndex]['url'];
        }

        $editionId = null;

        $this->em->wrapInTransaction(function () use (
            $draft,
            $owner,
            $artistName,
            $artistCanonical,
            $recordTitle,
            $recordCanonical,
            $yearOriginal,
            $resolved,
            $coverUrl,
            $formatEnum,
            &$editionId
        ) {
            // ===== ARTIST =====
            $artist = $this->em->getRepository(Artist::class)->findOneBy([
                'nameCanonical' => $artistCanonical,
                'countryCode' => $resolved['artist']['countryCode'] ?? 'XX',
            ]) ?? new Artist();

            $resolvedDiscogsArtistId = $resolved['artist']['discogsArtistId'] ?? null;

            $artist->setName($artistName)
                ->setNameCanonical($artistCanonical)
                ->setCountryCode($resolved['artist']['countryCode'] ?? 'XX')
                ->setCountryName(
                    (($resolved['artist']['countryCode'] ?? 'XX') === 'XX')
                        ? null
                        : ($resolved['artist']['countryName'] ?? null)
                );

            if ($resolvedDiscogsArtistId) {
                $artist->setDiscogsArtistId($resolvedDiscogsArtistId);
            }

            $this->em->persist($artist);

            // ===== RECORD =====
            $recordRepo = $this->em->getRepository(Record::class);
            $record = $recordRepo->findOneBy([
                'artist' => $artist,
                'titleCanonical' => $recordCanonical,
                'yearOriginal' => $yearOriginal,
            ]) ?? new Record();

            $isNewRecord = $record->getId() === null;

            $record->setArtist($artist)
                ->setTitle($recordTitle)
                ->setTitleCanonical($recordCanonical)
                ->setFormat($formatEnum)
                ->setYearOriginal($yearOriginal)
                ->setDiscogsMasterId($resolved['record']['discogsMasterId'] ?? null)
                ->setDiscogsReleaseId($resolved['record']['discogsReleaseId'] ?? null);

            // ===== COVER =====
            $localCoverPublicUrl = null;
            $localCoverHash = null;

            if ($coverUrl) {
                [$localCoverPublicUrl, $localCoverHash] = $this->ensureLocalCoverWithIdempotence(
                    $coverUrl,
                    $artistName,
                    $recordTitle,
                    $formatEnum
                );
            }

            if ($isNewRecord && $localCoverPublicUrl) {
                $record->setCoverFile($localCoverPublicUrl);
                $record->setCoverHash($localCoverHash);
            }

            $this->em->persist($record);

            // ===== EDITION =====
            $edition = (new Edition())
                ->setOwner($owner)
                ->setRecord($record)
                ->setFormat($formatEnum)
                ->setCoverFile($localCoverPublicUrl);

            $this->em->persist($edition);
            $this->em->remove($draft);

            $this->em->flush();

            $editionId = (int)$edition->getId();
        });

        return (int)$editionId;
    }

    private function resolveFormatEnum(string $primary, string $fallback): RecordFormat
    {
        $value = $this->normalizeFormatString($primary) ?: $this->normalizeFormatString($fallback) ?: 'Inconnu';

        return RecordFormat::tryFrom($value) ?? RecordFormat::UNKNOWN;
    }

    private function normalizeFormatString(?string $s): ?string
    {
        $s = trim((string)$s);
        if ($s === '') {
            return null;
        }
        $l = mb_strtolower($s, 'UTF-8');

        if ($l === '33t' || $l === '33' || str_contains($l, 'lp')) {
            return '33T';
        }
        if ($l === '45t' || $l === '45' || str_contains($l, '7"')) {
            return '45T';
        }
        if (str_contains($l, 'maxi') || str_contains($l, '12"')) {
            return 'Maxi45T';
        }
        if (str_contains($l, '78')) {
            return '78T';
        }
        if (str_contains($l, 'mix')) {
            return 'Mixte';
        }
        if ($l === 'inconnu') {
            return 'Inconnu';
        }

        if (in_array($s, ['33T', '45T', 'Maxi45T', '78T', 'Mixte', 'Inconnu'], true)) {
            return $s;
        }

        return null;
    }

    private function ensureLocalCoverWithIdempotence(
        string $srcUrlOrPath,
        string $artistName,
        string $recordTitle,
        RecordFormat $format
    ): array {
        $publicBase = '/uploads/cover';
        $targetDir = rtrim($this->uploadsDir, '/').'/cover';
        @mkdir($targetDir, 0775, true);

        $bytes = null;
        $srcLocalAbs = null;

        if (preg_match('#^https?://#i', $srcUrlOrPath) === 1) {
            $bytes = @file_get_contents($srcUrlOrPath);
            if ($bytes === false) {
                return [null, null];
            }
        } else {
            if (str_starts_with($srcUrlOrPath, '/uploads/')) {
                $srcLocalAbs = $this->absolutePublicPath($srcUrlOrPath);
            } else {
                $srcLocalAbs = $this->absolutePublicPath('/'.ltrim($srcUrlOrPath, '/'));
            }
            if (!$srcLocalAbs || !is_file($srcLocalAbs)) {
                return [null, null];
            }
        }

        $hash = $bytes !== null ? md5($bytes) : (@md5_file($srcLocalAbs) ?: null);
        if (!$hash) {
            return [null, null];
        }

        $existingUrl = $this->findExistingCoverByHash($hash);
        if ($existingUrl) {
            return [$existingUrl, $hash];
        }

        $basename = $this->buildCoverBaseName($artistName, $recordTitle, $format);
        $ext = $this->guessExtensionFromSource($srcUrlOrPath) ?? 'jpg';
        $ext = $this->normalizeExt($ext);

        $finalPath = $this->uniquePath($targetDir, $basename, $ext);
        $finalUrl = $publicBase.'/'.basename($finalPath);

        if ($bytes !== null) {
            @file_put_contents($finalPath, $bytes);
        } else {
            @rename($srcLocalAbs, $finalPath);
        }

        return [$finalUrl, $hash];
    }

    private function findExistingCoverByHash(string $hash): ?string
    {
        $rec = $this->em->getRepository(Record::class)->findOneBy(['coverHash' => $hash]);
        if (!$rec) {
            return null;
        }
        $url = $rec->getCoverFile();
        if (!$url) {
            return null;
        }
        $abs = $this->absolutePublicPath($url);

        return ($abs && is_file($abs)) ? $url : null;
    }

    private function absolutePublicPath(string $publicUrl): ?string
    {
        $publicRoot = dirname(rtrim($this->uploadsDir, '/'));

        return $publicRoot.$publicUrl;
    }

    private function buildCoverBaseName(string $artist, string $title, RecordFormat $format): string
    {
        $a = $this->slugifyForFilename($artist);
        $t = $this->slugifyForFilename($title);
        $f = $this->slugifyForFilename($format->value);

        return trim($a.'_'.$t.'_'.$f, '_');
    }

    private function slugifyForFilename(string $s): string
    {
        $s = trim($s);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('~[^A-Za-z0-9]+~', '-', $s) ?: '';
        $s = trim($s, '-');

        return strtolower($s);
    }

    private function guessExtensionFromSource(string $src): ?string
    {
        $lower = strtolower($src);
        foreach (['.jpg', '.jpeg', '.png', '.webp'] as $ext) {
            if (str_ends_with($lower, $ext)) {
                return $this->normalizeExt(ltrim($ext, '.'));
            }
        }
        if (preg_match('#^https?://#i', $src) === 1) {
            $headers = @get_headers($src, true);
            $ct = is_array($headers) ? ($headers['Content-Type'] ?? $headers['content-type'] ?? null) : null;
            $ct = is_array($ct) ? end($ct) : $ct;
            if (is_string($ct)) {
                return match (true) {
                    str_contains($ct, 'png') => 'png',
                    str_contains($ct, 'webp') => 'webp',
                    str_contains($ct, 'jpeg'),
                    str_contains($ct, 'jpg') => 'jpg',
                    default => null,
                };
            }
        }

        return null;
    }

    private function normalizeExt(string $ext): string
    {
        $e = strtolower($ext);

        return $e === 'jpeg' ? 'jpg' : $e;
    }

    private function uniquePath(string $dir, string $base, string $ext): string
    {
        $n = 0;
        do {
            $suffix = $n === 0 ? '' : '-'.($n + 1);
            $path = $dir.'/'.$base.$suffix.'.'.$ext;
            $n++;
        } while (file_exists($path));

        return $path;
    }
}
