<?php

namespace App\Controller\User;

use App\Dto\Location\AddLocationDto;
use App\Form\Location\AddLocationType;
use App\Form\User\ProfileType;
use App\Service\Location\LocationManager;
use App\Service\User\DeleteUserService;
use App\Service\User\EditUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile')]
    public function index(
        Request $request,
        EditUserService $editUserService,
        LocationManager $locationManager,
    ): Response {
        $user = $this->getUser();

        // ---- FORM PROFILE ----
        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $plainPassword = (string)$profileForm->get('password')->getData();
            $editUserService->editUser($user, $plainPassword);

            $this->addFlash('info', 'Votre profil a été modifié.');

            return $this->redirect($this->generateUrl('app_profile').'#infos', Response::HTTP_SEE_OTHER);
        }

        // ---- FORM LOCATIONS ----
        $locationDto = new AddLocationDto();
        $locationForm = $this->createForm(AddLocationType::class, $locationDto);
        $locationForm->handleRequest($request);

        if ($locationForm->isSubmitted() && $locationForm->isValid()) {
            $locationManager->addLocationForUser($user, $locationDto);

            $this->addFlash('success', 'Localisation ajoutée.');

            return $this->redirect($this->generateUrl('app_profile').'#localisations', Response::HTTP_SEE_OTHER);
        }

        $hasError = (
            ($profileForm->isSubmitted() && !$profileForm->isValid()) ||
            ($locationForm->isSubmitted() && !$locationForm->isValid())
        );

        return $this->render(
            'user/profile/index.html.twig',
            [
                'form' => $profileForm->createView(),
                'locationForm' => $locationForm->createView(),
            ],
            new Response(null, $hasError ? 422 : 200)
        );
    }

    #[Route('/profil/localisation/choisir/{id}', name: 'app_profile_location_select')]
    public function selectLocation(
        LocationManager $locationManager,
        string $id
    ): RedirectResponse {
        $locationManager->setCurrent($this->getUser(), $id);

        $this->addFlash('info', 'Votre localisation a changé');

        return $this->redirect($this->generateUrl('app_profile').'#localisations', Response::HTTP_SEE_OTHER);
    }

    #[Route('/profil/localisation/supprimer/{id}', name: 'app_profile_location_delete')]
    public function deleteLocation(
        LocationManager $locationManager,
        string $id
    ): RedirectResponse {
        $locationManager->removeUserLocation($this->getUser(), $id);

        $this->addFlash('danger', 'Votre localisation a été supprimée');

        return $this->redirect($this->generateUrl('app_profile').'#localisations', Response::HTTP_SEE_OTHER);
    }

    #[Route('/profil/demande-de-suppression', name: 'app_profile_mark_as_deleted')]
    public function markAccountAsDeleted(
        Security $security,
        DeleteUserService $deleteUserService
    ): RedirectResponse {
        $deleteUserService->markUserAsDeleted($this->getUser());
        $security->logout(false);

        $this->addFlash('warning', 'Votre compte a été désactivé<br/>Il sera supprimé dans 30 jours<br/>Pour le récupérer, <a href="#">contactez-nous</a>');

        return $this->redirectToRoute('app_logout', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/profil/supprimer', name: 'app_profile_delete')]
    public function deleteAccount(
        Security $security,
        DeleteUserService $deleteUserService
    ): RedirectResponse {
        $deleteUserService->hardDeleteUser($this->getUser());
        $security->logout(false);

        $this->addFlash('danger', 'Votre compte a été supprimé');

        return $this->redirectToRoute('app_logout', [], Response::HTTP_SEE_OTHER);
    }
}
