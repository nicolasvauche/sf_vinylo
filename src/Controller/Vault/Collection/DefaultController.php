<?php

namespace App\Controller\Vault\Collection;

use App\Entity\User\User;
use App\Service\Vault\CollectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    #[Route('/vault', name: 'app_vault_home')]
    public function index(
        Request $request,
        CollectionService $collectionService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $page = max(1, (int)$request->query->get('page', 1));
        $perPage = 6;

        $pagination = $collectionService->getCollectionPage($user, $page, $perPage);

        return $this->render('vault/collection/default/index.html.twig', [
            'items' => $pagination['items'],
            'total' => $pagination['total'],
            'pages' => $pagination['pages'],
            'page' => $pagination['page'],
            'perPage' => $pagination['perPage'],
        ]);
    }
}
