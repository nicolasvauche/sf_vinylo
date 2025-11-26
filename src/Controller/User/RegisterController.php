<?php

namespace App\Controller\User;

use App\Entity\User\User;
use App\Form\User\RegisterType;
use App\Service\User\RegisterUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    #[Route('/nouvel-utilisateur', name: 'app_register')]
    public function index(
        Request $request,
        RegisterUserService $registerUserService
    ): Response {
        $form = $this->createForm(RegisterType::class, new User())
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $user = $registerUserService->registerUser($user);

            $this->addFlash('success', "Bienvenue {$user->getPseudo()} !");

            return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render(
            'user/register/index.html.twig',
            [
                'form' => $form->createView(),
            ],
            new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200)
        );
    }
}
