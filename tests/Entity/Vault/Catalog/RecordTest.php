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
        $this->assertNull($record->getTitleCanonical());
        $this->assertNull($record->getSlug());
        $this->assertNull($record->getYearOriginal());
        $this->assertNull($record->getCoverFile());
        $this->assertNull($record->getCoverHash());
        $this->assertNull($record->getDiscogsMasterId());
        $this->assertNull($record->getDiscogsReleaseId());
        $this->assertNull($record->getArtist());
        $this->assertCount(0, $record->getEditions());
    }

    public function testSettersAndGetters(): void
    {
        $record = new Record();

        $this->assertSame($record, $record->setTitle('Legend'));
        $this->assertSame('Legend', $record->getTitle());
        $this->assertSame('legend', $record->getTitleCanonical());

        $this->assertSame($record, $record->setSlug('legend'));
        $this->assertSame($record, $record->setYearOriginal('1984'));
        $this->assertSame($record, $record->setCoverFile('covers/legend.jpg'));
        $this->assertSame($record, $record->setCoverHash('abc123hash'));
        $this->assertSame($record, $record->setDiscogsMasterId('12345'));
        $this->assertSame($record, $record->setDiscogsReleaseId('67890'));

        $this->assertSame('legend', $record->getSlug());
        $this->assertSame('1984', $record->getYearOriginal());
        $this->assertSame('covers/legend.jpg', $record->getCoverFile());
        $this->assertSame('abc123hash', $record->getCoverHash());
        $this->assertSame('12345', $record->getDiscogsMasterId());
        $this->assertSame('67890', $record->getDiscogsReleaseId());
    }

    public function testCanonicalizationDoesNotAlterTitleButUpdatesCanonical(): void
    {
        $record = new Record();

        $this->assertSame($record, $record->setTitle("  The   Wall "));
        $this->assertSame("  The   Wall ", $record->getTitle());
        $this->assertSame('the wall', $record->getTitleCanonical());
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
