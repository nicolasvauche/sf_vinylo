<?php

namespace App\Controller\Vault\Collection;

use App\Entity\Vault\Collection\Edition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ViewController extends AbstractController
{
    #[Route('/vault/disque/details/{id}', name: 'app_vault_edition_details')]
    #[IsGranted('edition.view', subject: 'edition')]
    public function index(Edition $edition): Response
    {
        return $this->render('vault/collection/view/index.html.twig', [
            'edition' => $edition,
        ]);
    }
}
