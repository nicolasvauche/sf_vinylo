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

    #[Route('/vault/disque/details', name: 'app_disk_details')]
    public function details(): Response
    {
        return $this->render('default/disk_details.html.twig');
    }

    #[Route('/vault/disque/supprimer', name: 'app_disk_delete')]
    public function diskDelete(): Response
    {
        $this->addFlash('danger', 'Le disque a été supprimé');

        return $this->redirectToRoute('app_vault');
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

    #[Route('/profil/supprimer', name: 'account_delete')]
    public function accountDelete(): Response
    {
        $this->addFlash('danger', 'Votre compte a été supprimé');

        return $this->redirectToRoute('app_register');
    }

    #[Route('/nouvel-utilisateur', name: 'app_register')]
    public function register(): Response
    {
        return $this->render('default/register.html.twig');
    }

    #[Route('/connexion', name: 'app_login')]
    public function login(): Response
    {
        return $this->render('default/login.html.twig');
    }

    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): Response
    {
        return $this->redirectToRoute('app_login');
    }
}
