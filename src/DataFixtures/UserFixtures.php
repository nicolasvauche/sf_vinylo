<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    const USERS = [
        [
            'pseudo' => 'nicolas',
            'email' => 'nvauche@gmail.com',
            'password' => 'mJhGFyAf7Lzx',
        ],
    ];

    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS as $user) {
            $entity = new User();
            $entity->setPseudo($user['pseudo'])
                ->setEmail($user['email'])
                ->setPassword($this->passwordHasher->hashPassword($entity, $user['password']));
            $manager->persist($entity);
            $this->addReference('user_'.$user['pseudo'], $entity);
        }

        $manager->flush();
    }
}
