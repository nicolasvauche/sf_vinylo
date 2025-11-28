<?php

namespace App\Service\Location;

use App\Dto\Location\AddLocationDto;
use App\Entity\Location\Address;
use App\Entity\Location\UserLocation;
use App\Entity\User\User;
use App\Repository\Location\AddressRepository;
use App\Repository\Location\UserLocationRepository;
use App\Repository\User\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class LocationManager
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly AddressRepository $addressRepository,
        private readonly UserLocationRepository $userLocationRepository,
        private readonly int $defaultRadiusMeters = 300,
        private readonly int $reuseProximityMeters = 5000,
    ) {
    }

    public function addLocationForUser(UserInterface $authenticatedUser, AddLocationDto $dto): UserLocation
    {
        /** @var User $user */
        $user = $this->userRepository->find($authenticatedUser->getId());

        $this->assertSuggestionSelected($dto);

        $address = $this->resolveOrCreateAddress($dto);

        $existing = $this->userLocationRepository->findOneBy([
            'owner' => $user,
            'address' => $address,
            'label' => $dto->label,
        ]);
        if ($existing instanceof UserLocation) {
            return $existing;
        }

        $favorite = (new UserLocation())
            ->setOwner($user)
            ->setAddress($address)
            ->setLabel($dto->label)
            ->setIsCurrent(false);

        $this->em->persist($favorite);
        $this->em->flush();

        return $favorite;
    }

    public function setCurrent(UserInterface $authenticatedUser, int $userLocationId): void
    {
        /** @var User $user */
        $user = $this->userRepository->find($authenticatedUser->getId());

        $target = $this->userLocationRepository->find($userLocationId);
        if (!$target instanceof UserLocation || $target->getOwner()?->getId() !== $user->getId()) {
            throw new \RuntimeException('Localisation introuvable pour cet utilisateur.');
        }

        $others = $this->userLocationRepository->findBy(['owner' => $user, 'isCurrent' => true]);
        foreach ($others as $loc) {
            if ($loc->getId() !== $target->getId()) {
                $loc->setIsCurrent(false);
            }
        }

        $target->setIsCurrent(true);
        $this->em->flush();
    }

    public function removeUserLocation(UserInterface $authenticatedUser, int $userLocationId): void
    {
        /** @var User $user */
        $user = $this->userRepository->find($authenticatedUser->getId());

        $loc = $this->userLocationRepository->find($userLocationId);
        if (!$loc instanceof UserLocation || $loc->getOwner()?->getId() !== $user->getId()) {
            throw new \RuntimeException('Localisation introuvable pour cet utilisateur.');
        }

        $this->em->remove($loc);
        $this->em->flush();
    }

    private function assertSuggestionSelected(AddLocationDto $dto): void
    {
        $placeId = trim((string)$dto->placeId);
        $locality = trim((string)$dto->locality);
        $countryCode = strtolower(trim((string)$dto->countryCode));
        $lat = trim((string)$dto->lat);
        $lng = trim((string)$dto->lng);

        if ($placeId === '' || $locality === '' || $countryCode === '' || $lat === '' || $lng === '') {
            throw new \InvalidArgumentException('Aucune suggestion valide n a été sélectionnée.');
        }
    }

    private function resolveOrCreateAddress(AddLocationDto $dto): Address
    {
        $placeId = trim((string)$dto->placeId);
        $displayName = trim((string)$dto->displayName);
        $locality = trim((string)$dto->locality);
        $countryCode = strtolower(trim((string)$dto->countryCode));
        $latStr = $this->roundCoord($dto->lat);
        $lngStr = $this->roundCoord($dto->lng);

        $address = $this->addressRepository->findOneBy(['placeId' => $placeId]);
        if ($address instanceof Address) {
            return $address;
        }

        $candidate = $this->findExistingNearbyAddress($locality, $countryCode, (float)$latStr, (float)$lngStr);
        if ($candidate instanceof Address) {
            return $candidate;
        }

        $address = (new Address())
            ->setPlaceId($placeId)
            ->setDisplayName($displayName)
            ->setCity($locality)
            ->setCountryCode($countryCode)
            ->setLat($latStr)
            ->setLng($lngStr);

        $this->em->persist($address);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->em->clear();
            $existing = $this->addressRepository->findOneBy(['placeId' => $placeId]);
            if ($existing instanceof Address) {
                return $existing;
            }
            throw new \RuntimeException('Conflit d unicité sur placeId non résolu.');
        }

        return $address;
    }

    private function findExistingNearbyAddress(string $locality, string $countryCode, float $lat, float $lng): ?Address
    {
        $candidates = $this->addressRepository->findBy([
            'city' => $locality,
            'countryCode' => $countryCode,
        ]);

        $best = null;
        $bestDist = PHP_FLOAT_MAX;

        foreach ($candidates as $addr) {
            $dist = $this->distanceMeters(
                (float)$addr->getLat(),
                (float)$addr->getLng(),
                $lat,
                $lng
            );
            if ($dist < $this->reuseProximityMeters && $dist < $bestDist) {
                $best = $addr;
                $bestDist = $dist;
            }
        }

        return $best;
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000.0;
        $toRad = static fn(float $deg): float => $deg * M_PI / 180.0;

        $dLat = $toRad($lat2 - $lat1);
        $dLon = $toRad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }

    private function roundCoord(string|float|int $value): string
    {
        return number_format((float)$value, 6, '.', '');
    }
}
