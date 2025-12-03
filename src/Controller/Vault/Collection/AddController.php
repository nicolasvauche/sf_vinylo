<?php

namespace App\Controller\Vault\Collection;

use App\Dto\Vault\Collection\AddEditionDto;
use App\Entity\User\User;
use App\Form\Vault\Collection\AddType;
use App\Form\Vault\Collection\ValidateType;
use App\Repository\Vault\Draft\EditionDraftRepository;
use App\Service\Vault\AddRecord\CheckDuplicateService;
use App\Service\Vault\AddRecord\CreateOrRetrieveDraftService;
use App\Service\Vault\AddRecord\FinalizeAddEditionService;
use App\Service\Vault\AddRecord\ValidateRecordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AddController extends AbstractController
{
    #[Route('/vault/collection/ajouter', name: 'app_vault_collection_add_form')]
    public function addRecord(
        Request $request,
        CreateOrRetrieveDraftService $starter
    ): Response {
        $data = new AddEditionDto();
        $form = $this->createForm(AddType::class, $data)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $draft = $starter->start($user, $data->artistName, $data->recordTitle);

            return $this->redirectToRoute('app_vault_collection_add_form_validate', [
                'id' => $draft->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('vault/collection/add/index.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route(
        '/vault/collection/ajouter/{id<\d+>}/valider',
        name: 'app_vault_collection_add_form_validate',
        methods: ['GET', 'POST']
    )]
    public function validateRecord(
        int $id,
        Request $request,
        EditionDraftRepository $draftRepo,
        CheckDuplicateService $dup,
        ValidateRecordService $validatorSvc,
        FinalizeAddEditionService $finalize,
    ): Response {
        $draft = $draftRepo->find($id);
        if (!$draft) {
            throw $this->createNotFoundException();
        }
        if ($draft->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $needsConfirmDuplicate = $dup->existsDuplicateForDraft($draft);

        $data = $validatorSvc->createDtoFromDraft($draft);

        $form = $this->createForm(ValidateType::class, $data)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();

            $validatorSvc->handleCoverChoice($data, $form);

            $validatorSvc->backfillFormatFromResolved($data, $draft);

            $editionId = $finalize->finalize($draft, $data, $user);

            $this->addFlash('success', 'Le disque a été ajouté à votre collection');

            return $this->redirectToRoute('app_vault_collection_add_form', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('vault/collection/validate/index.html.twig', [
            'form' => $form->createView(),
            'draft' => $draft,
            'needsConfirmDuplicate' => $needsConfirmDuplicate,
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }
}
