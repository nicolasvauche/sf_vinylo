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
    ) {
    }

    public function addLocationForUser(UserInterface $authenticatedUser, AddLocationDto $dto): UserLocation
    {
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
        if (
            empty($dto->placeId) ||
            $dto->lat === null || $dto->lng === null ||
            $dto->locality === null || $dto->countryCode === null
        ) {
            throw new \InvalidArgumentException('Aucune suggestion valide n a été sélectionnée.');
        }
    }

    private function resolveOrCreateAddress(AddLocationDto $dto): Address
    {
        $address = $this->addressRepository->findOneBy(['placeId' => $dto->placeId]);
        if ($address instanceof Address) {
            return $address;
        }

        $address = (new Address())
            ->setPlaceId($dto->placeId)
            ->setDisplayName($dto->displayName)
            ->setCity($dto->locality)
            ->setCountryCode(strtolower($dto->countryCode))
            ->setLat($this->roundCoord($dto->lat))
            ->setLng($this->roundCoord($dto->lng));

        $this->em->persist($address);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->em->clear();
            $existing = $this->addressRepository->findOneBy(['placeId' => $dto->placeId]);
            if ($existing instanceof Address) {
                return $existing;
            }
            throw new \RuntimeException('Conflit d unicité sur placeId non résolu.');
        }

        return $address;
    }

    private function roundCoord(string|float|int $value): string
    {
        return number_format((float)$value, 6, '.', '');
    }
}
