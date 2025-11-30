<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_flow_home');
    }

    #[Route('/vault', name: 'app_vault_home')]
    public function vault(): Response
    {
        return $this->render('default/vault.html.twig');
    }

    #[Route('/vault/disque/details', name: 'app_vault_edition_details')]
    public function details(): Response
    {
        return $this->render('default/disk_details.html.twig');
    }

    #[Route('/vault/disque/supprimer', name: 'app_vault_edition_delete')]
    public function diskDelete(): Response
    {
        $this->addFlash('danger', 'Le disque a été supprimé');

        return $this->redirectToRoute('app_vault_home');
    }

    #[Route('/flow', name: 'app_flow_home')]
    public function flow(): Response
    {
        return $this->render('default/flow.html.twig');
    }
}
