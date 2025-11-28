<?php

namespace App\Service\Location;

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
}
