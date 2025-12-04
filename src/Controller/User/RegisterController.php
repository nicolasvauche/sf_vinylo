<?php

namespace App\Controller\User;

use App\Entity\User\User;
use App\Form\User\RegisterType;
use App\Service\User\RegisterUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function index(
        Request $request,
        RegisterUserService $registerUserService,
        Security $security,
    ): Response {
        $form = $this->createForm(RegisterType::class, new User())
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $user = $registerUserService->registerUser($user);

            return $security->login($user, 'form_login', 'main');
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
