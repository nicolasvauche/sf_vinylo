<?php

namespace App\Service\Vault;

use App\Entity\User\User;
use App\Repository\Vault\Collection\EditionRepository;

final readonly class CollectionService
{
    public function __construct(private EditionRepository $editionRepository)
    {
    }

    public function getCollection(User $user): array
    {
        return $this->editionRepository->findUserCollectionSorted($user);
    }

    public function getCollectionPage(User $user, int $page, int $perPage = 6): array
    {
        $result = $this->editionRepository->paginateUserCollection($user, $page, $perPage);
        $effectivePage = min(max(1, $page), max(1, $result['pages']));

        return [
            'items' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $effectivePage,
            'perPage' => $perPage,
        ];
    }
}
