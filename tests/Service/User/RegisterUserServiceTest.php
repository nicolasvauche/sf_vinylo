<?php

namespace App\Tests\Service\User;

use App\Entity\User\User;
use App\Service\User\RegisterUserService;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegisterUserServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RegisterUserService $service;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(RegisterUserService::class);
        $this->hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        (new ORMPurger($this->em))->purge();
    }

    public function testRegisterHashesAndPersistsUser(): void
    {
        $user = (new User())
            ->setEmail('Nicolas.Example@TEST.com')
            ->setPseudo('Nico')
            ->setPassword('unTrèsGrosMotDePasse123!');

        $saved = $this->service->registerUser($user);

        $this->assertNotNull($saved->getId());

        $this->assertNotSame('unTrèsGrosMotDePasse123!', $saved->getPassword());
        $this->assertTrue(
            $this->hasher->isPasswordValid($saved, 'unTrèsGrosMotDePasse123!'),
            'Le hash stocké doit correspondre au mot de passe fourni.'
        );

        $found = $this->em->getRepository(User::class)->findOneBy(['email' => 'Nicolas.Example@TEST.com']);
        $this->assertNotNull($found);
        $this->assertSame($saved->getId(), $found->getId());
    }

    public function testDuplicateEmailRaisesConstraintViolation(): void
    {
        $u1 = (new User())->setEmail('dup@example.test')->setPseudo('A')->setPassword('passpasspasspass');
        $this->service->registerUser($u1);

        $u2 = (new User())->setEmail('dup@example.test')->setPseudo('B')->setPassword('passpasspasspass');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->service->registerUser($u2);
    }
}
