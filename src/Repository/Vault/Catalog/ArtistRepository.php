<?php

namespace App\Repository\Vault\Catalog;

use App\Entity\Vault\Catalog\Artist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Artist>
 */
class ArtistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Artist::class);
    }

    public function findOneByCanonical(string $canonical): ?Artist
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.nameCanonical = :canonical')
            ->setParameter('canonical', $canonical)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllByCanonical(string $canonical): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.nameCanonical = :canonical')
            ->setParameter('canonical', $canonical)
            ->getQuery()
            ->getResult();
    }

    public function findOneByCanonicalAndCountry(string $canonical, string $countryCode): ?Artist
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.nameCanonical = :canonical')
            ->andWhere('a.countryCode = :country')
            ->setParameter('canonical', $canonical)
            ->setParameter('country', $countryCode)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
