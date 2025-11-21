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
        return $this->render('default/index.html.twig');
    }

    #[Route('/vault', name: 'app_vault')]
    public function vault(): Response
    {
        return $this->render('default/vault.html.twig');
    }

    #[Route('/disque/details', name: 'app_disk_details')]
    public function details(): Response
    {
        return $this->render('default/disk_details.html.twig');
    }

    #[Route('/flow', name: 'app_flow')]
    public function flow(): Response
    {
        return $this->render('default/flow.html.twig');
    }

    #[Route('/profil', name: 'app_profile')]
    public function profile(): Response
    {
        return $this->render('default/profile.html.twig');
    }
}
