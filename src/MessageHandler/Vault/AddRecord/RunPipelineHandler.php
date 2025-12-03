<?php

namespace App\MessageHandler\Vault\AddRecord;

use App\Message\Vault\AddRecord\RunPipelineMessage;
use App\Repository\Vault\Draft\EditionDraftRepository;
use App\Service\Vault\AddRecord\Resolver\AddRecordResolver;
use App\Validator\Vault\AddRecord\ResolvedDraftValidator;
use App\ValueObject\Vault\AddRecord\DraftStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RunPipelineHandler
{
    public function __construct(
        private EditionDraftRepository $repo,
        private AddRecordResolver $resolver,
        private ResolvedDraftValidator $validator,
    ) {
    }

    public function __invoke(RunPipelineMessage $msg): void
    {
        $draft = $this->repo->find($msg->draftId);
        if (!$draft || $draft->getStatus() !== DraftStatus::PENDING) {
            return;
        }

        try {
            $discogs = $this->resolver->fetchDiscogs($draft);
            $draft->setDiscogs($discogs);

            $ai = $this->resolver->callAi($draft, $discogs);
            $draft->setAi($ai);

            $resolved = $this->resolver->buildResolved($draft, $discogs, $ai);
            $draft->setResolved($resolved);

            $errors = $this->validator->validate($resolved);
            if ($errors) {
                $draft->incAttempts();
                $draft->setLastError('resolved.invalid: '.implode(',', $errors));
                $this->repo->save($draft);

                return;
            }

            $draft->setDuplicateProbe([
                'ownerId' => $draft->getOwner()->getId(),
                'artistCanonical' => $resolved['artist']['nameCanonical'] ?? '',
                'recordCanonical' => $resolved['record']['titleCanonical'] ?? '',
                'yearOriginal' => $resolved['record']['yearOriginal'] ?? '0000',
            ]);

            $draft->setStatus(DraftStatus::READY);
            $draft->setLastError(null);
            $this->repo->save($draft);
        } catch (\Throwable $e) {
            $draft->incAttempts();
            $draft->setLastError($e->getMessage());
            $this->repo->save($draft);
        }
    }
}
