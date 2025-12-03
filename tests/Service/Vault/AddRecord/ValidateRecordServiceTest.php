<?php

namespace App\Tests\Service\Vault\AddRecord;

use App\Dto\Vault\Collection\ValidateEditionDto;
use App\Entity\Vault\Draft\EditionDraft;
use App\Service\Vault\AddRecord\ValidateRecordService;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(ValidateRecordService::class)]
final class ValidateRecordServiceTest extends KernelTestCase
{
    private ValidateRecordService $service;
    private FormFactoryInterface $formFactory;
    private string $tmpUploadsDir;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->tmpUploadsDir = sys_get_temp_dir().'/vinylo_test_uploads_'.bin2hex(random_bytes(4));
        @mkdir($this->tmpUploadsDir.'/cover', 0775, true);

        $this->service = new ValidateRecordService($this->tmpUploadsDir);

        $this->formFactory = self::getContainer()->get(FormFactoryInterface::class) ?? Forms::createFormFactory();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpUploadsDir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpUploadsDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
            @rmdir($this->tmpUploadsDir);
        }
        parent::tearDown();
    }

    private function makeTempUploadedFile(string $ext = 'jpg'): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'up_');

        if ($ext === 'png') {
            $bytes = "\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01"
                ."\x08\x02\x00\x00\x00\x90wS\xDE\x00\x00\x00\x0AIDATx\x9Cc``\x00\x00\x00\x02"
                ."\x00\x01E\x9Cl\xC6\x00\x00\x00\x00IEND\xAEB`\x82";
            $mime = 'image/png';
            $name = 'fake.png';
        } else {
            $bytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
                ."\xFF\xDB\x00C\x00".str_repeat("\x08", 64)
                ."\xFF\xC0\x00\x11\x08\x00\x01\x00\x01\x03\x01\x11\x00\x02\x11\x01\x03\x11\x01"
                ."\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"
                ."\xFF\xDA\x00\x08\x01\x01\x00\x00?\x00\xD2\xCF\x20\xFF\xD9";
            $mime = 'image/jpeg';
            $name = 'fake.jpg';
        }

        file_put_contents($tmpPath, $bytes);

        return new UploadedFile(
            $tmpPath,
            $name,
            $mime,
            null,
            true
        );
    }

    public function testCreateDtoFromDraftMapsResolved(): void
    {
        $draft = (new EditionDraft())
            ->setArtistCanonical('pink floyd')
            ->setRecordCanonical('the wall');

        $draft->setResolved([
            'artist' => [
                'name' => 'Pink Floyd',
                'countryCode' => 'GB',
                'countryName' => 'Royaume-Uni',
            ],
            'record' => [
                'title' => 'The Wall',
                'format' => '33T',
                'yearOriginal' => '1979',
                'discogsMasterId' => '11329',
                'discogsReleaseId' => null,
            ],
            'covers' => [
                ['url' => 'https://example.test/a.jpg', 'source' => 'discogs'],
                ['url' => 'https://example.test/b.jpg', 'source' => 'discogs'],
            ],
            'coverDefaultIndex' => 1,
        ]);

        $dto = $this->service->createDtoFromDraft($draft);

        self::assertSame('Pink Floyd', $dto->artistName);
        self::assertSame('GB', $dto->artistCountryCode);
        self::assertSame('Royaume-Uni', $dto->artistCountryName);

        self::assertSame('The Wall', $dto->recordTitle);
        self::assertSame('33T', $dto->recordFormat);
        self::assertSame('1979', $dto->recordYear);

        self::assertSame('11329', $dto->discogsMasterId);
        self::assertNull($dto->discogsReleaseId);

        self::assertCount(2, $dto->covers);
        self::assertSame(1, $dto->coverDefaultIndex);
        self::assertSame('discogs', $dto->recordCoverChoice);
    }

    public function testHandleCoverChoiceWithUploadReplacesListAndSetsIndex(): void
    {
        $dto = new ValidateEditionDto();
        $dto->covers = [
            ['url' => 'https://discogs.test/1.jpg', 'source' => 'discogs'],
            ['url' => 'https://discogs.test/2.jpg', 'source' => 'discogs'],
        ];
        $dto->coverDefaultIndex = 1;
        $dto->recordCoverChoice = 'upload';

        $form = $this->formFactory->createBuilder()
            ->add('recordCoverUpload', FileType::class, ['mapped' => false, 'required' => false])
            ->add('recordCoverCamera', FileType::class, ['mapped' => false, 'required' => false])
            ->getForm();

        $uploaded = $this->makeTempUploadedFile('jpg');
        $form->get('recordCoverUpload')->setData($uploaded);

        $this->service->handleCoverChoice($dto, $form);

        self::assertCount(1, $dto->covers);
        self::assertSame(0, $dto->coverDefaultIndex);
        self::assertSame('upload', $dto->covers[0]['source']);
        self::assertStringStartsWith('/uploads/cover/', $dto->covers[0]['url']);
        self::assertStringEndsWith('.jpg', $dto->covers[0]['url']);
    }

    public function testHandleCoverChoiceWithCameraReplacesListAndSetsIndex(): void
    {
        $dto = new ValidateEditionDto();
        $dto->covers = [
            ['url' => 'https://discogs.test/3.png', 'source' => 'discogs'],
        ];
        $dto->coverDefaultIndex = 0;
        $dto->recordCoverChoice = 'camera';

        $form = $this->formFactory->createBuilder()
            ->add('recordCoverUpload', FileType::class, ['mapped' => false, 'required' => false])
            ->add('recordCoverCamera', FileType::class, ['mapped' => false, 'required' => false])
            ->getForm();

        $camera = $this->makeTempUploadedFile('png');
        $form->get('recordCoverCamera')->setData($camera);

        $this->service->handleCoverChoice($dto, $form);

        self::assertCount(1, $dto->covers);
        self::assertSame(0, $dto->coverDefaultIndex);
        self::assertSame('camera', $dto->covers[0]['source']);
        self::assertStringStartsWith('/uploads/cover/', $dto->covers[0]['url']);
        self::assertStringEndsWith('.png', $dto->covers[0]['url']);
    }

    public function testHandleCoverChoiceKeepsDiscogsWhenNoFileGiven(): void
    {
        $dto = new ValidateEditionDto();
        $dto->covers = [
            ['url' => 'https://discogs.test/a.webp', 'source' => 'discogs'],
            ['url' => 'https://discogs.test/b.webp', 'source' => 'discogs'],
        ];
        $dto->coverDefaultIndex = 1;
        $dto->recordCoverChoice = 'upload';

        $form = $this->formFactory->createBuilder()
            ->add('recordCoverUpload', FileType::class, ['mapped' => false, 'required' => false])
            ->add('recordCoverCamera', FileType::class, ['mapped' => false, 'required' => false])
            ->getForm();

        $this->service->handleCoverChoice($dto, $form);

        self::assertCount(2, $dto->covers);
        self::assertSame(1, $dto->coverDefaultIndex);
        self::assertSame('discogs', $dto->covers[0]['source']);
    }

    public function testBackfillFormatFromResolvedOnlyWhenEmpty(): void
    {
        $draft = new EditionDraft();
        $draft->setResolved([
            'record' => ['format' => '33T'],
        ]);

        $dto = new ValidateEditionDto();
        $dto->recordFormat = '';

        $this->service->backfillFormatFromResolved($dto, $draft);
        self::assertSame('33T', $dto->recordFormat);

        $dto->recordFormat = '45T';
        $this->service->backfillFormatFromResolved($dto, $draft);
        self::assertSame('45T', $dto->recordFormat);
    }
}
