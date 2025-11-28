<?php

namespace App\Security;

use App\Entity\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->getDeletedAt() !== null) {
            throw new CustomUserMessageAccountStatusException(
                'Ce compte est en cours de suppression<br/>Pour le récupérer, <a href="#">contactez-nous</a>'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
