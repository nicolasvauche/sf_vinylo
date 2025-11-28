<?php

namespace App\Tests\Service\Location;

use App\Dto\Location\AddLocationDto;
use App\Entity\Location\UserLocation;
use App\Entity\User\User;
use App\Service\Location\LocationManager;
use App\Service\Location\UserLocationService;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserLocationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserLocationService $service;
    private LocationManager $locationManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(UserLocationService::class);
        $this->locationManager = self::getContainer()->get(LocationManager::class);

        (new ORMPurger($this->em))->purge();
    }

    public function testGetUserLocationsReturnsOnlyOwnersLocations(): void
    {
        $u1 = $this->createUser('alice@example.test', 'Alice');
        $u2 = $this->createUser('bob@example.test', 'Bob');

        $l1 = $this->locationManager->addLocationForUser(
            $u1,
            $this->makeDto('Home', 'pid-a', 'X', 'Gouzon', 'fr', 46.19, 2.20)
        );
        $l2 = $this->locationManager->addLocationForUser(
            $u1,
            $this->makeDto('Work', 'pid-b', 'Y', 'Gouzon', 'fr', 46.20, 2.21)
        );

        $this->locationManager->addLocationForUser(
            $u2,
            $this->makeDto('Spot', 'pid-c', 'Z', 'Gouzon', 'fr', 46.21, 2.22)
        );

        $list = $this->service->getUserLocations($u1);

        $this->assertIsArray($list);
        $this->assertCount(2, $list, 'u1 doit récupérer uniquement ses 2 localisations');
        $this->assertContainsOnlyInstancesOf(UserLocation::class, $list);

        $ids = array_map(fn(UserLocation $ul) => $ul->getId(), $list);
        $this->assertContains($l1->getId(), $ids);
        $this->assertContains($l2->getId(), $ids);
    }

    public function testGetCurrentUserLocationReturnsCurrentWhenSet(): void
    {
        $u = $this->createUser('carol@example.test', 'Carol');

        $home = $this->locationManager->addLocationForUser(
            $u,
            $this->makeDto('Home', 'pid-1', 'X', 'Gouzon', 'fr', 46.19, 2.20)
        );
        $work = $this->locationManager->addLocationForUser(
            $u,
            $this->makeDto('Work', 'pid-2', 'Y', 'Gouzon', 'fr', 46.20, 2.21)
        );

        $this->locationManager->setCurrent($u, $work->getId());

        $current = $this->service->getCurrentUserLocation($u);

        $this->assertInstanceOf(UserLocation::class, $current);
        $this->assertSame($work->getId(), $current->getId());
    }

    public function testGetCurrentUserLocationReturnsNullWhenNone(): void
    {
        $u = $this->createUser('dave@example.test', 'Dave');

        $this->locationManager->addLocationForUser(
            $u,
            $this->makeDto('Place', 'pid-x', 'X', 'Gouzon', 'fr', 46.215, 2.205)
        );

        $current = $this->service->getCurrentUserLocation($u);

        $this->assertNull(
            $current,
            'Sans sélection, le service doit retourner null (ou lever une exception si c’est ton choix).'
        );
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
}
