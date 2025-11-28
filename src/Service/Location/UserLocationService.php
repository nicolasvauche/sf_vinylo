<?php

namespace App\Service\Location;

use App\Entity\Location\UserLocation;
use App\Entity\User\User;
use App\Repository\Location\UserLocationRepository;

final readonly class UserLocationService
{
    public function __construct(
        private UserLocationRepository $repository,
    ) {
    }

    public function getUserLocations(User $user): array
    {
        return $this->repository->findBy(['owner' => $user]);
    }

    public function getCurrentUserLocation(User $user): ?UserLocation
    {
        return $this->repository->findOneBy(['owner' => $user, 'isCurrent' => true]);
    }
}
