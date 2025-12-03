<?php

namespace App\Service\Vault\AddRecord;

use App\Entity\User\User;
use App\Entity\Vault\Draft\EditionDraft;
use App\Message\Vault\AddRecord\RunPipelineMessage;
use App\Repository\Vault\Draft\EditionDraftRepository;
use App\Service\Vault\AddRecord\Normalization\Canonicalizer;
use App\ValueObject\Vault\AddRecord\DraftStatus;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateOrRetrieveDraftService
{
    public function __construct(
        private EditionDraftRepository $repo,
        private Canonicalizer $canonicalizer,
        private MessageBusInterface $bus,
        private int $draftTtlHours = 24,
    ) {
    }

    public function start(User $owner, string $artistRaw, string $recordRaw): EditionDraft
    {
        $artistCanonical = $this->canonicalizer->canonicalize($artistRaw);
        $recordCanonical = $this->canonicalizer->canonicalize($recordRaw);

        $existing = $this->repo->findActiveByOwnerAndCanonicals($owner, $artistCanonical, $recordCanonical);
        if ($existing) {
            return $existing;
        }

        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d hours', $this->draftTtlHours));
        $draft = (new EditionDraft())
            ->setOwner($owner)
            ->setArtistCanonical($artistCanonical)
            ->setRecordCanonical($recordCanonical)
            ->setStatus(DraftStatus::PENDING)
            ->setInput([
                'artistRaw' => trim($artistRaw),
                'recordRaw' => trim($recordRaw),
                'artistCanonical' => $artistCanonical,
                'recordCanonical' => $recordCanonical,
            ])
            ->setExpiresAt($expiresAt);

        $this->repo->save($draft);

        $this->bus->dispatch(new RunPipelineMessage($draft->getId()));

        return $draft;
    }
}
