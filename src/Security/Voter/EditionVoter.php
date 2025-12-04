<?php

namespace App\Security\Voter;

use App\Entity\User\User;
use App\Entity\Vault\Collection\Edition;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class EditionVoter extends Voter
{
    public const VIEW = 'edition.view';
    public const EDIT = 'edition.edit';
    public const DELETE = 'edition.delete';

    public function __construct(private Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof Edition) {
            return false;
        }

        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var User|null $user */
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false; // non connecté
        }

        /** @var Edition $edition */
        $edition = $subject;

        $isOwner = $edition->getOwner()?->getId() === $user->getId();
        if (!$isOwner) {
            return false;
        }

        return match ($attribute) {
            self::VIEW, self::EDIT, self::DELETE => true,
            default => false,
        };
    }
}
