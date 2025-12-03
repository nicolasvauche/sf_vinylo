<?php

namespace App\Repository\Vault\Catalog;

use App\Entity\Vault\Catalog\Artist;
use App\Entity\Vault\Catalog\Record;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Record>
 */
class RecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Record::class);
    }

    public function findOneByArtistCanonicalAndYear(
        Artist $artist,
        string $titleCanonical,
        string $yearOriginal
    ): ?Record {
        return $this->createQueryBuilder('r')
            ->andWhere('r.artist = :artist')
            ->andWhere('r.titleCanonical = :canonical')
            ->andWhere('r.yearOriginal = :year')
            ->setParameter('artist', $artist)
            ->setParameter('canonical', $titleCanonical)
            ->setParameter('year', $yearOriginal)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
