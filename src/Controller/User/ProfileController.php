<?php

namespace App\Controller\User;

use App\Form\User\ProfileType;
use App\Service\User\EditUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile')]
    public function index(
        Request $request,
        EditUserService $editUserService,
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string)$form->get('password')->getData();
            $user = $editUserService->editUser($user, $plainPassword);

            $this->addFlash('info', 'Votre profil a été modifié');

            return $this->redirectToRoute('app_profile', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render(
            'user/profile/index.html.twig',
            [
                'form' => $form->createView(),
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
