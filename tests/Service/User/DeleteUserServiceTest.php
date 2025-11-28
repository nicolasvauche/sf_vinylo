<?php

namespace App\Tests\Service\User;

use App\Entity\User\User;
use App\Service\User\DeleteUserService;
use App\Repository\User\UserRepository;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DeleteUserServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DeleteUserService $service;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(DeleteUserService::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);

        (new ORMPurger($this->em))->purge();
    }

    public function testMarkUserAsDeletedSetsDeletedAtAndIsIdempotent(): void
    {
        $u = $this->createUser('alice@example.test', 'Alice');

        $this->service->markUserAsDeleted($u);
        $this->em->clear();
        $reloaded = $this->userRepository->find($u->getId());

        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertNotNull($reloaded->getDeletedAt(), 'deletedAt doit être renseigné au premier appel');

        $firstDeletedAt = $reloaded->getDeletedAt();

        $this->service->markUserAsDeleted($reloaded);
        $this->em->clear();
        $reloaded2 = $this->userRepository->find($u->getId());

        $this->assertEquals(
            $firstDeletedAt,
            $reloaded2->getDeletedAt(),
            'Deuxième appel idempotent : la date ne doit pas changer'
        );
    }

    public function testHardDeleteUserRemovesEntity(): void
    {
        $u = $this->createUser('bob@example.test', 'Bob');
        $uId = (string)$u->getId();

        $this->service->hardDeleteUser($u);
        $this->em->clear();

        $deleted = $this->userRepository->find($uId);
        $this->assertNull($deleted, 'L’utilisateur doit être supprimé de la base');
    }

    public function testGetDaysBeforeRemoveReturnsZeroWhenNotMarked(): void
    {
        $u = $this->createUser('carol@example.test', 'Carol');

        $days = $this->service->getDaysBeforeRemove($u);
        $this->assertSame(0, $days, 'Sans deletedAt, le service doit retourner 0');
    }

    public function testGetDaysBeforeRemoveReturnsExpectedWhenMarked(): void
    {
        $u = $this->createUser('dave@example.test', 'Dave');
        $uId = (string)$u->getId();

        $yesterday = (new \DateTimeImmutable())->modify('-1 day');
        $u->setDeletedAt($yesterday);
        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->userRepository->find($uId);
        $days = $this->service->getDaysBeforeRemove($reloaded);

        $this->assertSame(30, $days, 'Avec deletedAt à J-1 et délai +31d, il reste 30 jours');
    }

    public function testGetDaysBeforeRemoveReturnsZeroWhenPastDeadline(): void
    {
        $u = $this->createUser('erin@example.test', 'Erin');

        $fortyDaysAgo = (new \DateTimeImmutable())->modify('-40 days');
        $u->setDeletedAt($fortyDaysAgo);
        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->userRepository->find($u->getId());
        $days = $this->service->getDaysBeforeRemove($reloaded);

        $this->assertSame(0, $days, 'Quand la deadline est dépassée, la méthode doit renvoyer 0');
    }

    private function createUser(string $email, string $pseudo): User
    {
        $u = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo)
            ->setPassword('irrelevant-for-test');

        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }
}
