<?php

namespace App\Controller\Vault\Collection;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AddController extends AbstractController
{
    #[Route('/vault/collection/ajouter', name: 'app_vault_collection_add_form')]
    public function index(): Response
    {
        return $this->render('vault/collection/add/index.html.twig', [
            'controller_name' => 'AddController',
        ]);
    }
}
