<?php

namespace App\Service\User;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class DeleteUserService
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function markUserAsDeleted(UserInterface $authenticatedUser): void
    {
        $user = $this->userRepository->find($authenticatedUser->getId());
        if (!$user || $user->getDeletedAt()) {
            return;
        }

        $user->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function hardDeleteUser(UserInterface $authenticatedUser): void
    {
        $user = $this->userRepository->find($authenticatedUser->getId());
        if (!$user) {
            return;
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
