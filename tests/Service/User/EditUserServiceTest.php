<?php

namespace App\Tests\Service\User;

use App\Entity\User\User;
use App\Service\User\EditUserService;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class EditUserServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private EditUserService $service;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(EditUserService::class);
        $this->hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        (new ORMPurger($this->em))->purge();
    }

    private function createPersistedUser(string $email, string $pseudo, string $plainPassword): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testEditUserWithNullPasswordDoesNotChangeHash(): void
    {
        $user = $this->createPersistedUser('user1@example.test', 'Nico', 'InitialPass123!');

        $originalHash = $user->getPassword();

        $updated = $this->service->editUser($user, null);

        $found = $this->em->getRepository(User::class)->find($user->getId());

        $this->assertSame($user->getId(), $updated->getId());
        $this->assertSame(
            $originalHash,
            $found->getPassword(),
            'Le hash ne doit pas être modifié si password est null.'
        );
        $this->assertTrue($this->hasher->isPasswordValid($found, 'InitialPass123!'));
    }

    public function testEditUserWithEmptyPasswordDoesNotChangeHash(): void
    {
        $user = $this->createPersistedUser('user2@example.test', 'Nico', 'InitialPass123!');

        $originalHash = $user->getPassword();

        $updated = $this->service->editUser($user, '');

        $found = $this->em->getRepository(User::class)->find($user->getId());

        $this->assertSame($user->getId(), $updated->getId());
        $this->assertSame(
            $originalHash,
            $found->getPassword(),
            'Le hash ne doit pas être modifié si password est vide.'
        );
        $this->assertTrue($this->hasher->isPasswordValid($found, 'InitialPass123!'));
    }

    public function testEditUserWithNewPasswordUpdatesHash(): void
    {
        $user = $this->createPersistedUser('user3@example.test', 'Nico', 'InitialPass123!');

        $originalHash = $user->getPassword();

        $updated = $this->service->editUser($user, 'NewUltraStrongP@ssw0rd');

        $found = $this->em->getRepository(User::class)->find($user->getId());

        $this->assertSame($user->getId(), $updated->getId());
        $this->assertNotSame(
            $originalHash,
            $found->getPassword(),
            'Le hash doit changer quand un nouveau mot de passe est fourni.'
        );
        $this->assertTrue($this->hasher->isPasswordValid($found, 'NewUltraStrongP@ssw0rd'));
        $this->assertFalse($this->hasher->isPasswordValid($found, 'InitialPass123!'));
    }
}

