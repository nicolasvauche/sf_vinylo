<?php

namespace App\Tests\Entity\Vault\Catalog;

use App\Entity\Vault\Catalog\Artist;
use App\Entity\Vault\Catalog\Record;
use App\Entity\Vault\Collection\Edition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Record::class)]
final class RecordTest extends TestCase
{
    public function testInitialState(): void
    {
        $record = new Record();

        $this->assertNull($record->getId());
        $this->assertNull($record->getTitle());
        $this->assertNull($record->getSlug());
        $this->assertNull($record->getYearOriginal());
        $this->assertNull($record->getCoverUrl());
        $this->assertNull($record->getCoverHash());
        $this->assertNull($record->getDiscogsMasterId());
        $this->assertNull($record->getDiscogsReleaseId());
        $this->assertNull($record->getSourceConfidence());
        $this->assertNull($record->getArtist());
        $this->assertCount(0, $record->getEditions());
    }

    public function testSettersAndGetters(): void
    {
        $record = new Record();

        $this->assertSame($record, $record->setTitle('Legend'));
        $this->assertSame($record, $record->setSlug('legend'));
        $this->assertSame($record, $record->setYearOriginal('1984'));
        $this->assertSame($record, $record->setCoverUrl('https://exemple.test/legend.jpg'));
        $this->assertSame($record, $record->setCoverHash('abc123hash'));
        $this->assertSame($record, $record->setDiscogsMasterId('12345'));
        $this->assertSame($record, $record->setDiscogsReleaseId('67890'));
        $this->assertSame($record, $record->setSourceConfidence(85));

        $this->assertSame('Legend', $record->getTitle());
        $this->assertSame('legend', $record->getSlug());
        $this->assertSame('1984', $record->getYearOriginal());
        $this->assertSame('https://exemple.test/legend.jpg', $record->getCoverUrl());
        $this->assertSame('abc123hash', $record->getCoverHash());
        $this->assertSame('12345', $record->getDiscogsMasterId());
        $this->assertSame('67890', $record->getDiscogsReleaseId());
        $this->assertSame(85, $record->getSourceConfidence());
    }

    public function testArtistAssociation(): void
    {
        $artist = (new Artist())->setName('Bob Marley & The Wailers');
        $record = (new Record())->setTitle('Legend');

        $this->assertNull($record->getArtist());
        $this->assertSame($record, $record->setArtist($artist));
        $this->assertSame($artist, $record->getArtist());
    }

    public function testAddEditionSetsBackReferenceAndIsIdempotent(): void
    {
        $record = (new Record())->setTitle('Legend');

        $edition = new Edition();

        $record->addEdition($edition);
        $this->assertCount(1, $record->getEditions());
        $this->assertSame($record, $edition->getRecord());

        $record->addEdition($edition);
        $this->assertCount(1, $record->getEditions());
        $this->assertSame($record, $edition->getRecord());
    }

    public function testRemoveEditionClearsOwningSide(): void
    {
        $record = (new Record())->setTitle('Legend');
        $edition = new Edition();

        $record->addEdition($edition);
        $this->assertCount(1, $record->getEditions());
        $this->assertSame($record, $edition->getRecord());

        $record->removeEdition($edition);
        $this->assertCount(0, $record->getEditions());
        $this->assertNull($edition->getRecord());
    }
}
