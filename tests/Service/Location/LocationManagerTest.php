<?php

namespace App\Tests\Service\Location;

use App\Dto\Location\AddLocationDto;
use App\Entity\Location\Address;
use App\Entity\Location\UserLocation;
use App\Entity\User\User;
use App\Service\Location\LocationManager;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocationManagerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LocationManager $service;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(LocationManager::class);

        (new ORMPurger($this->em))->purge();
    }

    public function testAddLocationCreatesAddressAndUserLocation(): void
    {
        $user = $this->createUser('alice@example.test', 'Alice');

        $dto = $this->makeDto(
            label: 'maison',
            placeId: 'osm:node:123',
            displayName: 'Gouzon, Creuse, Nouvelle-Aquitaine, France',
            locality: 'Gouzon',
            countryCode: 'fr',
            lat: 46.195432,
            lng: 2.206789
        );

        $loc = $this->service->addLocationForUser($user, $dto);

        $this->assertInstanceOf(UserLocation::class, $loc);
        $this->assertNotNull($loc->getId());
        $this->assertSame('maison', $loc->getLabel());
        $this->assertFalse($loc->isCurrent());

        $this->assertInstanceOf(Address::class, $loc->getAddress());
        $this->assertSame('osm:node:123', $loc->getAddress()->getPlaceId());

        $this->assertGreaterThan(0, $this->countEntities(UserLocation::class));
        $this->assertGreaterThan(0, $this->countEntities(Address::class));
    }

    public function testSetCurrentSwitchesFlags(): void
    {
        $user = $this->createUser('bob@example.test', 'Bob');

        $home = $this->service->addLocationForUser(
            $user,
            $this->makeDto(
                'home',
                'pid-1',
                'X',
                'Gouzon',
                'fr',
                46.19,
                2.20
            )
        );
        $work = $this->service->addLocationForUser(
            $user,
            $this->makeDto(
                'work',
                'pid-2',
                'Y',
                'Gouzon',
                'fr',
                46.20,
                2.21
            )
        );

        $this->service->setCurrent($user, $home->getId());
        $this->em->clear();

        /** @var UserLocation $homeR */
        $homeR = $this->em->getRepository(UserLocation::class)->find($home->getId());
        /** @var UserLocation $workR */
        $workR = $this->em->getRepository(UserLocation::class)->find($work->getId());

        $this->assertTrue($homeR->isCurrent());
        $this->assertFalse($workR->isCurrent());

        $this->service->setCurrent($user, $work->getId());
        $this->em->clear();

        $homeR = $this->em->getRepository(UserLocation::class)->find($home->getId());
        $workR = $this->em->getRepository(UserLocation::class)->find($work->getId());

        $this->assertFalse($homeR->isCurrent());
        $this->assertTrue($workR->isCurrent());
    }

    public function testRemoveUserLocationDeletesOrphanAddress(): void
    {
        $user = $this->createUser('carol@example.test', 'Carol');

        $loc = $this->service->addLocationForUser(
            $user,
            $this->makeDto('spot', 'pid-z', 'Z', 'Gouzon', 'fr', 46.21, 2.22)
        );

        $locId = (string)$loc->getId();
        $addressId = (string)$loc->getAddress()->getId();

        $this->service->removeUserLocation($user, $locId);

        $this->em->clear();

        $this->assertNull(
            $this->em->getRepository(UserLocation::class)->find($locId),
            'La UserLocation doit être supprimée'
        );
        $this->assertNull(
            $this->em->getRepository(Address::class)->find($addressId),
            'L’Address orpheline doit être supprimée'
        );
    }

    public function testRemoveUserLocationKeepsAddressIfStillReferenced(): void
    {
        $user1 = $this->createUser('dave@example.test', 'Dave');
        $user2 = $this->createUser('erin@example.test', 'Erin');

        $dto1 = $this->makeDto('L1', 'same-place', 'X', 'Gouzon', 'fr', 46.22, 2.23);
        $dto2 = $this->makeDto('L2', 'same-place', 'X', 'Gouzon', 'fr', 46.2201, 2.2301);

        $loc1 = $this->service->addLocationForUser($user1, $dto1);
        $loc2 = $this->service->addLocationForUser($user2, $dto2);

        $loc1Id = (string)$loc1->getId();
        $addressId = (string)$loc1->getAddress()->getId();

        $this->assertSame(
            $addressId,
            (string)$loc2->getAddress()->getId(),
            'Les deux Localisations doivent référencer la même Address'
        );

        $this->service->removeUserLocation($user1, $loc1Id);
        $this->em->clear();

        $this->assertNull($this->em->getRepository(UserLocation::class)->find($loc1Id));
        $this->assertNotNull(
            $this->em->getRepository(Address::class)->find($addressId),
            'L’Address ne doit pas être supprimée tant qu’elle est référencée'
        );
    }

    public function testAddLocationWithoutValidSuggestionThrows(): void
    {
        $user = $this->createUser('frank@example.test', 'Frank');

        $dto = $this->makeDto(
            label: 'invalid',
            placeId: '',
            displayName: 'X',
            locality: '',
            countryCode: 'fr',
            lat: '',
            lng: ''
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service->addLocationForUser($user, $dto);
    }

    private function createUser(string $email, string $pseudo): User
    {
        $u = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo)
            ->setPassword('irrelevant-here');
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    private function makeDto(
        string $label,
        string $placeId,
        string $displayName,
        string $locality,
        string $countryCode,
        string|float $lat,
        string|float $lng
    ): AddLocationDto {
        $dto = new AddLocationDto();
        $dto->label = $label;
        $dto->placeId = $placeId;
        $dto->displayName = $displayName;
        $dto->locality = $locality;
        $dto->countryCode = $countryCode;
        $dto->lat = (string)$lat;
        $dto->lng = (string)$lng;

        return $dto;
    }

    private function countEntities(string $entityClass): int
    {
        return $this->em->getRepository($entityClass)->count([]);
    }
}
