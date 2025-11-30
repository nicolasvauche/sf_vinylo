<?php

namespace App\Controller\Vault\Collection;

use App\Dto\Vault\Collection\AddEditionDto;
use App\Dto\Vault\Collection\EditEditionDto;
use App\Form\Vault\Collection\AddType;
use App\Form\Vault\Collection\CreateType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AddController extends AbstractController
{
    #[Route('/vault/collection/ajouter', name: 'app_vault_collection_add_form')]
    public function addRecord(Request $request): Response
    {
        $data = new AddEditionDto();

        $form = $this->createForm(AddType::class, $data)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'Vous pouvez maintenant personnaliser votre édition');

            return $this->redirectToRoute('app_vault_collection_add_form_validate', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('vault/collection/add/index.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/vault/collection/ajouter/valider', name: 'app_vault_collection_add_form_validate')]
    public function validateRecord(Request $request): Response
    {
        $data = new EditEditionDto();

        $form = $this->createForm(CreateType::class, $data)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'Le disque a été ajouté à votre collection');

            return $this->redirectToRoute('app_vault_collection_add_form', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('vault/collection/validate/index.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }
}
