<?php

namespace App\Tests\Entity\Location;

use App\Entity\Location\Address;
use App\Entity\Location\UserLocation;
use App\Entity\User\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserLocation::class)]
final class UserLocationTest extends TestCase
{
    public function testInitialState(): void
    {
        $fav = new UserLocation();

        $this->assertNull($fav->getId());
        $this->assertNull($fav->getLabel());
        $this->assertFalse($fav->isCurrent());
        $this->assertNull($fav->getOwner());
        $this->assertNull($fav->getAddress());
    }

    public function testSettersAndGetters(): void
    {
        $fav = new UserLocation();

        $user = new User();
        $address = (new Address())
            ->setPlaceId('987654321')
            ->setDisplayName('Boulevard Saint Michel, Paris, France')
            ->setCity('Paris')
            ->setCountryCode('fr')
            ->setLat('48.846000')
            ->setLng('2.343000');

        $label = 'Travail';

        $this->assertSame($fav, $fav->setLabel($label));
        $this->assertSame($fav, $fav->setIsCurrent(true));
        $this->assertSame($fav, $fav->setOwner($user));
        $this->assertSame($fav, $fav->setAddress($address));

        $this->assertSame($label, $fav->getLabel());
        $this->assertTrue($fav->isCurrent());
        $this->assertSame($user, $fav->getOwner());
        $this->assertSame($address, $fav->getAddress());
    }

    public function testToggleIsCurrent(): void
    {
        $fav = new UserLocation();

        $this->assertFalse($fav->isCurrent());
        $fav->setIsCurrent(true);
        $this->assertTrue($fav->isCurrent());
        $fav->setIsCurrent(false);
        $this->assertFalse($fav->isCurrent());
    }
}

