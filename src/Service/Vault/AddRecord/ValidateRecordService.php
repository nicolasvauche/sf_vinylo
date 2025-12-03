<?php

namespace App\Service\Vault\AddRecord;

use App\Dto\Vault\Collection\ValidateEditionDto;
use App\Entity\Vault\Draft\EditionDraft;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormInterface;

final readonly class ValidateRecordService
{
    public function __construct(
        #[Autowire(param: 'app.uploads_directory')] private string $uploadsDir,
    ) {
    }

    public function createDtoFromDraft(EditionDraft $draft): ValidateEditionDto
    {
        $resolved = $draft->getResolved() ?? [];

        $data = new ValidateEditionDto();
        $data->artistName = (string)($resolved['artist']['name'] ?? '');
        $data->artistCountryCode = (string)($resolved['artist']['countryCode'] ?? 'XX');
        $data->artistCountryName = $data->artistCountryCode === 'XX'
            ? null
            : (string)($resolved['artist']['countryName'] ?? null);

        $data->recordTitle = (string)($resolved['record']['title'] ?? '');
        $data->recordFormat = (string)($resolved['record']['format'] ?? 'Inconnu');
        $data->recordYear = (string)($resolved['record']['yearOriginal'] ?? '0000');

        $data->discogsMasterId = $resolved['record']['discogsMasterId'] ?? null;
        $data->discogsReleaseId = $resolved['record']['discogsReleaseId'] ?? null;

        $data->recordCoverChoice = 'discogs';
        $data->covers = $resolved['covers'] ?? [];
        $data->coverDefaultIndex = (int)($resolved['coverDefaultIndex'] ?? 0);

        return $data;
    }

    public function handleCoverChoice(ValidateEditionDto $data, FormInterface $form): void
    {
        $choice = $data->recordCoverChoice ?? 'discogs';
        /** @var UploadedFile|null $uploadFile */
        $uploadFile = $form->has('recordCoverUpload') ? $form->get('recordCoverUpload')->getData() : null;
        /** @var UploadedFile|null $cameraFile */
        $cameraFile = $form->has('recordCoverCamera') ? $form->get('recordCoverCamera')->getData() : null;

        $targetDir = rtrim($this->uploadsDir, '/').'/cover';
        @mkdir($targetDir, 0775, true);

        if ($choice === 'upload' && $uploadFile instanceof UploadedFile) {
            $publicUrl = $this->moveUploadedCover($uploadFile, $targetDir);
            if ($publicUrl) {
                $data->covers = [['url' => $publicUrl, 'source' => 'upload']];
                $data->coverDefaultIndex = 0;
            }

            return;
        }

        if ($choice === 'camera' && $cameraFile instanceof UploadedFile) {
            $publicUrl = $this->moveUploadedCover($cameraFile, $targetDir);
            if ($publicUrl) {
                $data->covers = [['url' => $publicUrl, 'source' => 'camera']];
                $data->coverDefaultIndex = 0;
            }

            return;
        }

        if (ctype_digit((string)$choice)) {
            $idx = (int)$choice;
            if (isset($data->covers[$idx])) {
                $data->coverDefaultIndex = $idx;
            }
        }
    }

    public function backfillFormatFromResolved(ValidateEditionDto $data, EditionDraft $draft): void
    {
        if (!empty($data->recordFormat)) {
            return;
        }
        $resolved = $draft->getResolved() ?? [];
        if (!empty($resolved['record']['format'])) {
            $data->recordFormat = (string)$resolved['record']['format'];
        }
    }

    private function moveUploadedCover(UploadedFile $file, string $targetDir): ?string
    {
        $ext = strtolower($file->guessExtension() ?: 'jpg');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $filename = sprintf('cover_%s.%s', bin2hex(random_bytes(8)), $ext);

        try {
            $file->move($targetDir, $filename);
        } catch (\Throwable) {
            return null;
        }

        return '/uploads/cover/'.$filename;
    }
}
