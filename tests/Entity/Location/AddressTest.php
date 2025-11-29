<?php

namespace App\Tests\Entity\Location;

use App\Entity\Location\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
final class AddressTest extends TestCase
{
    public function testInitialState(): void
    {
        $address = new Address();

        $this->assertNull($address->getId());
        $this->assertNull($address->getPlaceId());
        $this->assertNull($address->getDisplayName());
        $this->assertNull($address->getCity());
        $this->assertNull($address->getCountryCode());
        $this->assertNull($address->getLat());
        $this->assertNull($address->getLng());
    }

    public function testSettersAndGetters(): void
    {
        $address = new Address();

        $placeId = '123456789';
        $displayName = '12 Rue des Forges, 23230 Gouzon, France';
        $city = 'Gouzon';
        $country = 'fr';
        $lat = '46.224500';
        $lng = '2.194300';

        $this->assertSame($address, $address->setPlaceId($placeId));
        $this->assertSame($address, $address->setDisplayName($displayName));
        $this->assertSame($address, $address->setCity($city));
        $this->assertSame($address, $address->setCountryCode($country));
        $this->assertSame($address, $address->setLat($lat));
        $this->assertSame($address, $address->setLng($lng));

        $this->assertSame($placeId, $address->getPlaceId());
        $this->assertSame($displayName, $address->getDisplayName());
        $this->assertSame($city, $address->getCity());
        $this->assertSame($country, $address->getCountryCode());
        $this->assertSame($lat, $address->getLat());
        $this->assertSame($lng, $address->getLng());
    }
}

