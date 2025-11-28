<?php

namespace App\Controller\Ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ModalController extends AbstractController
{
    #[Route('/modal/confirm', name: 'modal_confirm')]
    public function confirm(Request $request): Response
    {
        $title = $request->get('title') ?? 'Êtes-vous sûr ?';
        $message = $request->get('message') ?? 'Cette action est irréversible.';

        return $this->render('_layout/modal/_confirm.html.twig', [
            'title' => $title,
            'message' => $message,
        ]);
    }

    #[Route('/modal/moods', name: 'modal_moods')]
    public function moods(Request $request): Response
    {
        return $this->render('_layout/modal/_moods.html.twig', ['initial' => $request->get('initial') ?? '']);
    }

    #[Route('/modal/playlists', name: 'modal_playlists')]
    public function playlists(Request $request): Response
    {
        return $this->render('_layout/modal/_playlists.html.twig', ['initial' => $request->get('initial') ?? '']);
    }
}
