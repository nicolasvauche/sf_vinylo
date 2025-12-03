<?php

namespace App\Tests\Entity\Vault\Draft;

use App\Entity\User\User;
use App\Entity\Vault\Draft\EditionDraft;
use App\ValueObject\Vault\AddRecord\DraftStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditionDraft::class)]
final class EditionDraftTest extends TestCase
{
    public function testInitialState(): void
    {
        $draft = new EditionDraft();

        $this->assertNull($draft->getId());
        $this->assertNull($draft->getArtistCanonical());
        $this->assertNull($draft->getRecordCanonical());
        $this->assertSame(DraftStatus::PENDING, $draft->getStatus());

        $this->assertSame([], $draft->getInput());
        $this->assertNull($draft->getDiscogs());
        $this->assertNull($draft->getAi());
        $this->assertNull($draft->getResolved());
        $this->assertNull($draft->getDuplicateProbe());

        $this->assertSame(0, $draft->getAttempts());
        $this->assertNull($draft->getLastError());

        $this->assertNull($draft->getCreatedAt());
        $this->assertNull($draft->getUpdatedAt());
        $this->assertNull($draft->getExpiresAt());
        $this->assertNull($draft->getOwner());
    }

    public function testSettersAndGetters(): void
    {
        $draft = new EditionDraft();

        $owner = new User();

        $artistCanonical = 'pink floyd';
        $recordCanonical = 'the wall';
        $status = DraftStatus::READY;

        $input = ['artistRaw' => 'Pink', 'recordRaw' => 'Wall'];
        $discogs = ['candidates' => [['id' => 1]]];
        $ai = ['artist' => ['displayName' => 'Pink Floyd']];
        $resolved = ['artist' => ['name' => 'Pink Floyd'], 'record' => ['title' => 'The Wall']];
        $duplicateProbe = ['exists' => false];

        $lastError = 'network timeout';
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2025-01-01 10:05:00');
        $expiresAt = new \DateTimeImmutable('2025-01-02 00:00:00');

        $this->assertSame($draft, $draft->setArtistCanonical($artistCanonical));
        $this->assertSame($draft, $draft->setRecordCanonical($recordCanonical));
        $this->assertSame($draft, $draft->setStatus($status));
        $this->assertSame($draft, $draft->setInput($input));
        $this->assertSame($draft, $draft->setDiscogs($discogs));
        $this->assertSame($draft, $draft->setAi($ai));
        $this->assertSame($draft, $draft->setResolved($resolved));
        $this->assertSame($draft, $draft->setDuplicateProbe($duplicateProbe));
        $this->assertSame($draft, $draft->setLastError($lastError));
        $this->assertSame($draft, $draft->setCreatedAt($createdAt));
        $this->assertSame($draft, $draft->setUpdatedAt($updatedAt));
        $this->assertSame($draft, $draft->setExpiresAt($expiresAt));
        $this->assertSame($draft, $draft->setOwner($owner));

        $this->assertSame($artistCanonical, $draft->getArtistCanonical());
        $this->assertSame($recordCanonical, $draft->getRecordCanonical());
        $this->assertSame($status, $draft->getStatus());
        $this->assertSame($input, $draft->getInput());
        $this->assertSame($discogs, $draft->getDiscogs());
        $this->assertSame($ai, $draft->getAi());
        $this->assertSame($resolved, $draft->getResolved());
        $this->assertSame($duplicateProbe, $draft->getDuplicateProbe());
        $this->assertSame($lastError, $draft->getLastError());
        $this->assertSame($createdAt, $draft->getCreatedAt());
        $this->assertSame($updatedAt, $draft->getUpdatedAt());
        $this->assertSame($expiresAt, $draft->getExpiresAt());
        $this->assertSame($owner, $draft->getOwner());
    }

    public function testIncAttempts(): void
    {
        $draft = new EditionDraft();
        $this->assertSame(0, $draft->getAttempts());

        $draft->incAttempts();
        $this->assertSame(1, $draft->getAttempts());

        $draft->incAttempts()->incAttempts();
        $this->assertSame(3, $draft->getAttempts());
    }

    public function testNullableJsonFields(): void
    {
        $draft = new EditionDraft();

        $draft->setDiscogs(['foo' => 'bar']);
        $draft->setAi(['x' => 1]);
        $draft->setResolved(['ok' => true]);
        $draft->setDuplicateProbe(['exists' => true]);

        $this->assertNotNull($draft->getDiscogs());
        $this->assertNotNull($draft->getAi());
        $this->assertNotNull($draft->getResolved());
        $this->assertNotNull($draft->getDuplicateProbe());

        $draft->setDiscogs(null)
            ->setAi(null)
            ->setResolved(null)
            ->setDuplicateProbe(null);

        $this->assertNull($draft->getDiscogs());
        $this->assertNull($draft->getAi());
        $this->assertNull($draft->getResolved());
        $this->assertNull($draft->getDuplicateProbe());
    }

    public function testStatusDefaultAndChange(): void
    {
        $draft = new EditionDraft();

        $this->assertSame(DraftStatus::PENDING, $draft->getStatus());

        $draft->setStatus(DraftStatus::READY);
        $this->assertSame(DraftStatus::READY, $draft->getStatus());

        $draft->setStatus(DraftStatus::DONE);
        $this->assertSame(DraftStatus::DONE, $draft->getStatus());
    }
}
