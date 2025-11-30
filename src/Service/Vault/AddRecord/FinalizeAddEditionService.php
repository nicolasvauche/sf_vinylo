<?php

namespace App\Service\Vault\AddRecord;

use App\Dto\Vault\Collection\EditEditionDto;
use App\Entity\User\User;
use App\Entity\Vault\Catalog\Artist;
use App\Entity\Vault\Catalog\Record;
use App\Entity\Vault\Collection\Edition;
use App\Entity\Vault\Draft\EditionDraft;
use App\ValueObject\Vault\AddRecord\DraftStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FinalizeAddEditionService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function finalize(EditionDraft $draft, EditEditionDto $data, User $owner): int
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
        $yearOriginal = (string)$data->recordYear ?: $yearOriginalCanonical;

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
            &$editionId
        ) {
            // ARTIST
            $artist = $this->em->getRepository(Artist::class)->findOneBy([
                'nameCanonical' => $artistCanonical,
                'countryCode' => $resolved['artist']['countryCode'] ?? ($resolved['artist']['countryCode'] ?? 'XX'),
            ]) ?? new Artist();

            $artist->setName($artistName)
                ->setNameCanonical($artistCanonical)
                ->setCountryCode($resolved['artist']['countryCode'] ?? 'XX')
                ->setCountryName(
                    (($resolved['artist']['countryCode'] ?? 'XX') === 'XX') ? null : ($resolved['artist']['countryName'] ?? null)
                )
                ->setDiscogsArtistId($resolved['artist']['discogsArtistId'] ?? null);

            $this->em->persist($artist);

            // RECORD
            $record = $this->em->getRepository(Record::class)->findOneBy([
                'artist' => $artist,
                'titleCanonical' => $recordCanonical,
                'yearOriginal' => $yearOriginal,
            ]) ?? new Record();

            $record->setArtist($artist)
                ->setTitle($recordTitle)
                ->setTitleCanonical($recordCanonical)
                ->setYearOriginal($yearOriginal)
                ->setDiscogsMasterId($resolved['record']['discogsMasterId'] ?? null)
                ->setDiscogsReleaseId($resolved['record']['discogsReleaseId'] ?? null);

            $this->em->persist($record);

            // EDITION
            $edition = (new Edition())
                ->setOwner($owner)
                ->setRecord($record)
                ->setCoverFile($coverUrl);

            $this->em->persist($edition);

            $this->em->remove($draft);

            // Écrire réellement
            $this->em->flush();

            $editionId = (int)$edition->getId();
        });

        return (int)$editionId;
    }
}
