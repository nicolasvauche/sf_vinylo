<?php

namespace App\Service\Vault\AddRecord;

use App\Entity\Vault\Collection\Edition;
use App\Entity\Vault\Draft\EditionDraft;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CheckDuplicateService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function existsDuplicateForDraft(EditionDraft $draft): bool
    {
        $probe = $draft->getDuplicateProbe();

        if (!is_array($probe)) {
            $resolved = $draft->getResolved() ?? [];
            $probe = [
                'ownerId' => (int)$draft->getOwner()->getId(),
                'artistCanonical' => (string)($resolved['artist']['nameCanonical'] ?? ''),
                'recordCanonical' => (string)($resolved['record']['titleCanonical'] ?? ''),
                'yearOriginal' => (string)($resolved['record']['yearOriginal'] ?? '0000'),
            ];
        }

        $qb = $this->em->createQueryBuilder();
        $qb->select('e.id')
            ->from(Edition::class, 'e')
            ->join('e.record', 'r')
            ->join('r.artist', 'a')
            ->andWhere('IDENTITY(e.owner) = :ownerId')
            ->andWhere('a.nameCanonical = :a')
            ->andWhere('r.titleCanonical = :t')
            ->andWhere('r.yearOriginal = :y')
            ->setMaxResults(1)
            ->setParameter('ownerId', (int)$probe['ownerId'])
            ->setParameter('a', $probe['artistCanonical'])
            ->setParameter('t', $probe['recordCanonical'])
            ->setParameter('y', $probe['yearOriginal']);

        return (bool)$qb->getQuery()->getOneOrNullResult();
    }
}
