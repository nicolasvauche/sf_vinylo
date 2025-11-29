<?php

namespace App\Tests\Entity\Vault\Collection;

use App\Entity\User\User;
use App\Entity\Vault\Catalog\Record;
use App\Entity\Vault\Collection\Edition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Edition::class)]
final class EditionTest extends TestCase
{
    public function testInitialState(): void
    {
        $edition = new Edition();

        $this->assertNull($edition->getId());
        $this->assertNull($edition->getCoverUrl());
        $this->assertNull($edition->getCreatedAt());
        $this->assertNull($edition->getUpdatedAt());
        $this->assertNull($edition->getOwner());
        $this->assertNull($edition->getRecord());
    }

    public function testSettersAndGetters(): void
    {
        $edition = new Edition();

        $user = new User();
        $record = (new Record())->setTitle('Legend');

        $coverUrl = 'https://example.test/custom-cover.jpg';
        $createdAt = new \DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2025-01-02 12:34:56');

        $this->assertSame($edition, $edition->setCoverUrl($coverUrl));
        $this->assertSame($edition, $edition->setCreatedAt($createdAt));
        $this->assertSame($edition, $edition->setUpdatedAt($updatedAt));
        $this->assertSame($edition, $edition->setOwner($user));
        $this->assertSame($edition, $edition->setRecord($record));

        $this->assertSame($coverUrl, $edition->getCoverUrl());
        $this->assertSame($createdAt, $edition->getCreatedAt());
        $this->assertSame($updatedAt, $edition->getUpdatedAt());
        $this->assertSame($user, $edition->getOwner());
        $this->assertSame($record, $edition->getRecord());
    }

    public function testCanChangeAssociations(): void
    {
        $edition = new Edition();

        $user1 = new User();
        $user2 = new User();
        $record1 = (new Record())->setTitle('First');
        $record2 = (new Record())->setTitle('Second');

        $edition->setOwner($user1);
        $edition->setRecord($record1);

        $this->assertSame($user1, $edition->getOwner());
        $this->assertSame($record1, $edition->getRecord());

        $edition->setOwner($user2);
        $edition->setRecord($record2);

        $this->assertSame($user2, $edition->getOwner());
        $this->assertSame($record2, $edition->getRecord());
    }

    public function testCoverUrlNullable(): void
    {
        $edition = new Edition();

        $edition->setCoverUrl('https://example.test/a.jpg');
        $this->assertSame('https://example.test/a.jpg', $edition->getCoverUrl());

        $edition->setCoverUrl(null);
        $this->assertNull($edition->getCoverUrl());
    }
}
