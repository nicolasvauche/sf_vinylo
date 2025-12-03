<?php

namespace App\Repository\Vault\Draft;

use App\Entity\User\User;
use App\Entity\Vault\Draft\EditionDraft;
use App\ValueObject\Vault\AddRecord\DraftStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EditionDraft>
 */
class EditionDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EditionDraft::class);
    }

    public function save(EditionDraft $draft): void
    {
        $em = $this->getEntityManager();
        $em->persist($draft);
        $em->flush();
    }

    public function remove(EditionDraft $draft): void
    {
        $em = $this->getEntityManager();
        $em->remove($draft);
        $em->flush();
    }

    public function findActiveByOwnerAndCanonicals(
        User $owner,
        string $artistCanonical,
        string $recordCanonical
    ): ?EditionDraft {
        return $this->createQueryBuilder('d')
            ->andWhere('d.owner = :owner')
            ->andWhere('d.artistCanonical = :a')
            ->andWhere('d.recordCanonical = :r')
            ->andWhere('d.status IN (:active)')
            ->andWhere('d.expiresAt > :now')
            ->setParameter('owner', $owner)
            ->setParameter('a', $artistCanonical)
            ->setParameter('r', $recordCanonical)
            ->setParameter('active', [DraftStatus::PENDING, DraftStatus::READY])
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function purgeExpired(): int
    {
        return $this->createQueryBuilder('d')
            ->delete()
            ->andWhere('d.expiresAt <= :now OR d.status IN (:terminal)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('terminal', [DraftStatus::DONE, DraftStatus::CANCELLED])
            ->getQuery()
            ->execute();
    }
}
