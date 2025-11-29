<?php

namespace App\Security;

use App\Entity\User\User;
use App\Service\User\DeleteUserService;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserChecker implements UserCheckerInterface
{
    public function __construct(private DeleteUserService $deleteUserService)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->getDeletedAt() !== null) {
            $daysBeforeRemove = $this->deleteUserService->getDaysBeforeRemove($user);
            throw new CustomUserMessageAccountStatusException(
                "Ce compte est désactivé et sera définitivement supprimé dans $daysBeforeRemove jour".($daysBeforeRemove > 1 ? 's' : '').".<br/>Pour le récupérer, <a href=\"#\">contactez-nous</a>"
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
