<?php

namespace App\Repository\Vault\Collection;

use App\Entity\User\User;
use App\Entity\Vault\Collection\Edition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Edition>
 */
class EditionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Edition::class);
    }

    public function findUserCollectionSorted(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.record', 'r')
            ->leftJoin('r.artist', 'a')
            ->addSelect('r', 'a')
            ->andWhere('e.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('a.nameCanonical', 'ASC')
            ->addOrderBy('r.yearOriginal', 'ASC')
            ->addOrderBy('r.titleCanonical', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function baseQbForUser(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.record', 'r')->addSelect('r')
            ->leftJoin('r.artist', 'a')->addSelect('a')
            ->andWhere('e.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('a.nameCanonical', 'ASC')
            ->addOrderBy('r.yearOriginal', 'ASC')
            ->addOrderBy('r.titleCanonical', 'ASC');
    }

    public function paginateUserCollection(User $user, int $page, int $perPage = 6): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $qb = $this->baseQbForUser($user)
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb);
        $total = count($paginator);
        $pages = max(1, (int)ceil($total / $perPage));

        if ($page > $pages) {
            $qb = $this->baseQbForUser($user)
                ->setFirstResult(($pages - 1) * $perPage)
                ->setMaxResults($perPage);
            $paginator = new Paginator($qb);
        }

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => $total,
            'pages' => $pages,
        ];
    }
}
