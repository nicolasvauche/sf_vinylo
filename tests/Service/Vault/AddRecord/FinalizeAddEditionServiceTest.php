<?php

namespace App\Tests\Service\Vault\AddRecord;

use App\Dto\Vault\Collection\ValidateEditionDto;
use App\Entity\User\User;
use App\Entity\Vault\Collection\Edition;
use App\Entity\Vault\Draft\EditionDraft;
use App\Service\Vault\AddRecord\FinalizeAddEditionService;
use App\ValueObject\Vault\AddRecord\DraftStatus;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(FinalizeAddEditionService::class)]
final class FinalizeAddEditionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private FinalizeAddEditionService $service;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(FinalizeAddEditionService::class);

        (new ORMPurger($this->em))->purge();
    }

    private function fakeRemoteCoverUrl(): string
    {
        return 'file:///definitely/not/there.jpg';
    }

    public function testFinalizeCreatesArtistRecordEditionAndRemovesDraft(): void
    {
        $owner = (new User())
            ->setEmail('owner@example.test')
            ->setPseudo('Owner')
            ->setPassword('x');
        $this->em->persist($owner);
        $this->em->flush();

        $draft = (new EditionDraft())
            ->setOwner($owner)
            ->setStatus(DraftStatus::READY)
            ->setArtistCanonical('pink-floyd')
            ->setRecordCanonical('the-wall')
            ->setExpiresAt(new \DateTimeImmutable('+1 day'))
            ->setResolved([
                'artist' => [
                    'nameCanonical' => 'pink-floyd',
                    'countryCode' => 'GB',
                    'countryName' => 'UK',
                    'discogsArtistId' => 123,
                ],
                'record' => [
                    'titleCanonical' => 'the-wall',
                    'yearOriginal' => '1979',
                    'format' => '33T',
                    'discogsMasterId' => 999,
                ],
            ]);
        $this->em->persist($draft);
        $this->em->flush();
        $draftId = $draft->getId();

        $dto = new ValidateEditionDto();
        $dto->artistName = 'Pink Floyd';
        $dto->recordTitle = 'The Wall';
        $dto->recordYear = '1979';
        $dto->recordFormat = '33T';
        $dto->covers = [
            ['url' => $this->fakeRemoteCoverUrl(), 'source' => 'upload'],
        ];
        $dto->coverDefaultIndex = 0;

        $editionId = $this->service->finalize($draft, $dto, $owner);
        $this->assertIsInt($editionId);

        $edition = $this->em->getRepository(Edition::class)->find($editionId);
        $this->assertNotNull($edition);

        $record = $edition->getRecord();
        $artist = $record->getArtist();

        // ==== ARTIST CHECKS ====
        $this->assertSame('Pink Floyd', $artist->getName());
        $this->assertSame('pink-floyd', $artist->getNameCanonical());
        $this->assertSame('GB', $artist->getCountryCode());
        $this->assertSame('UK', $artist->getCountryName());
        $this->assertSame(123, (int)$artist->getDiscogsArtistId());

        // ==== RECORD CHECKS ====
        $this->assertSame('The Wall', $record->getTitle());
        $this->assertSame('the-wall', $record->getTitleCanonical());
        $this->assertSame('1979', $record->getYearOriginal());
        $this->assertSame('33T', $record->getFormat()->value);
        $this->assertSame(999, (int)$record->getDiscogsMasterId());

        // ==== EDITION CHECKS ====
        $this->assertSame($owner, $edition->getOwner());
        $this->assertSame('33T', $edition->getFormat()->value);

        // ==== DRAFT REMOVED ====
        $this->assertNull($this->em->getRepository(EditionDraft::class)->find($draftId));
    }

    public function testFinalizationReusesExistingRecordAndAvoidsDuplicateCoverCopy(): void
    {
        $owner = (new User())
            ->setEmail('user2@test')
            ->setPseudo('User2')
            ->setPassword('x');
        $this->em->persist($owner);
        $this->em->flush();

        $draft1 = (new EditionDraft())
            ->setOwner($owner)
            ->setStatus(DraftStatus::READY)
            ->setArtistCanonical('daft-punk')
            ->setRecordCanonical('discovery')
            ->setExpiresAt(new \DateTimeImmutable('+1 day'))
            ->setResolved([
                'artist' => [
                    'nameCanonical' => 'daft-punk',
                    'countryCode' => 'FR',
                ],
                'record' => [
                    'titleCanonical' => 'discovery',
                    'yearOriginal' => '2001',
                    'format' => '33T',
                ],
            ]);
        $this->em->persist($draft1);
        $this->em->flush();

        $dto1 = new ValidateEditionDto();
        $dto1->artistName = 'Daft Punk';
        $dto1->recordTitle = 'Discovery';
        $dto1->recordYear = '2001';
        $dto1->recordFormat = '33T';
        $dto1->covers = [
            ['url' => $this->fakeRemoteCoverUrl(), 'source' => 'upload'],
        ];
        $dto1->coverDefaultIndex = 0;

        $edition1Id = $this->service->finalize($draft1, $dto1, $owner);
        $edition1 = $this->em->getRepository(Edition::class)->find($edition1Id);
        $this->assertNotNull($edition1);

        $record = $edition1->getRecord();

        $draft2 = (new EditionDraft())
            ->setOwner($owner)
            ->setStatus(DraftStatus::READY)
            ->setArtistCanonical('daft-punk')
            ->setRecordCanonical('discovery')
            ->setExpiresAt(new \DateTimeImmutable('+1 day'))
            ->setResolved([
                'artist' => ['nameCanonical' => 'daft-punk', 'countryCode' => 'FR'],
                'record' => ['titleCanonical' => 'discovery', 'yearOriginal' => '2001', 'format' => '33T'],
            ]);
        $this->em->persist($draft2);
        $this->em->flush();

        $dto2 = clone $dto1;

        $edition2Id = $this->service->finalize($draft2, $dto2, $owner);
        $edition2 = $this->em->getRepository(Edition::class)->find($edition2Id);
        $this->assertNotNull($edition2);

        $this->assertSame(
            $record->getId(),
            $edition2->getRecord()->getId(),
            'Le même Record doit être réutilisé'
        );

        $this->assertSame($edition1->getRecord()->getCoverHash(), $edition2->getRecord()->getCoverHash());
    }

    public function testFinalizeRejectsInvalidOwner(): void
    {
        $ownerA = (new User())->setEmail('A@test')->setPseudo('A')->setPassword('x');
        $ownerB = (new User())->setEmail('B@test')->setPseudo('B')->setPassword('x');
        $this->em->persist($ownerA);
        $this->em->persist($ownerB);
        $this->em->flush();

        $draft = (new EditionDraft())
            ->setOwner($ownerA)
            ->setStatus(DraftStatus::READY)
            ->setArtistCanonical('x')
            ->setRecordCanonical('y')
            ->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $this->em->persist($draft);
        $this->em->flush();

        $dto = new ValidateEditionDto();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Draft ownership mismatch');

        $this->service->finalize($draft, $dto, $ownerB);
    }

    public function testFinalizeRejectsWhenDraftNotReady(): void
    {
        $owner = (new User())->setEmail('C@test')->setPseudo('C')->setPassword('x');
        $this->em->persist($owner);
        $this->em->flush();

        $draft = (new EditionDraft())
            ->setOwner($owner)
            ->setStatus(DraftStatus::PENDING)
            ->setArtistCanonical('whatever')
            ->setRecordCanonical('something')
            ->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $this->em->persist($draft);
        $this->em->flush();

        $dto = new ValidateEditionDto();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Draft not READY');

        $this->service->finalize($draft, $dto, $owner);
    }
}
