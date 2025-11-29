<?php

namespace App\Tests\Entity\Vault\Catalog;

use App\Entity\Vault\Catalog\Artist;
use App\Entity\Vault\Catalog\Record;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Artist::class)]
final class ArtistTest extends TestCase
{
    public function testInitialState(): void
    {
        $artist = new Artist();

        $this->assertNull($artist->getId());
        $this->assertNull($artist->getName());
        $this->assertNull($artist->getNameCanonical());
        $this->assertNull($artist->getSlug());
        $this->assertNull($artist->getCountryCode());
        $this->assertNull($artist->getCountryName());
        $this->assertNull($artist->getDiscogsArtistId());
        $this->assertCount(0, $artist->getRecords());
    }

    public function testSettersAndGetters(): void
    {
        $artist = new Artist();

        $this->assertSame($artist, $artist->setName('Bob Marley & The Wailers'));
        $this->assertSame('Bob Marley & The Wailers', $artist->getName());
        $this->assertSame('bob marley & the wailers', $artist->getNameCanonical());

        $this->assertSame($artist, $artist->setSlug('bob-marley-the-wailers'));
        $this->assertSame($artist, $artist->setCountryCode('JM'));
        $this->assertSame($artist, $artist->setCountryName('Jamaica'));
        $this->assertSame($artist, $artist->setDiscogsArtistId('12345'));

        $this->assertSame('bob-marley-the-wailers', $artist->getSlug());
        $this->assertSame('JM', $artist->getCountryCode());
        $this->assertSame('Jamaica', $artist->getCountryName());
        $this->assertSame('12345', $artist->getDiscogsArtistId());

        $this->assertSame($artist, $artist->setNameCanonical('bob marley + wailers'));
        $this->assertSame('Bob Marley & The Wailers', $artist->getName());
        $this->assertSame('bob marley + wailers', $artist->getNameCanonical());
    }

    public function testCanonicalizationDoesNotAlterNameButUpdatesCanonical(): void
    {
        $artist = new Artist();
        $artist->setName("   Pink    Floyd  ");

        $this->assertSame("   Pink    Floyd  ", $artist->getName());
        $this->assertSame('pink floyd', $artist->getNameCanonical());
    }

    public function testAddRecordSetsBackReferenceAndIsIdempotent(): void
    {
        $artist = new Artist();
        $artist->setName('Artist');

        $record = new Record();
        $record->setTitle('Legend');

        $artist->addRecord($record);
        $this->assertCount(1, $artist->getRecords());
        $this->assertSame($artist, $record->getArtist());

        $artist->addRecord($record);
        $this->assertCount(1, $artist->getRecords());
        $this->assertSame($artist, $record->getArtist());
    }

    public function testRemoveRecordClearsOwningSide(): void
    {
        $artist = new Artist();
        $artist->setName('Artist');

        $record = new Record();
        $record->setTitle('Legend');

        $artist->addRecord($record);
        $this->assertCount(1, $artist->getRecords());
        $this->assertSame($artist, $record->getArtist());

        $artist->removeRecord($record);
        $this->assertCount(0, $artist->getRecords());
        $this->assertNull($record->getArtist());
    }
}
