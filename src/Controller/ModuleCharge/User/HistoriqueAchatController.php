<?php

namespace App\Controller\ModuleCharge\User;

use App\Entity\HistoriqueAchat;
use App\Form\HistoriqueAchatType;
use App\Repository\HistoriqueAchatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/historique/achat')]
final class HistoriqueAchatController extends AbstractController
{
    #[Route(name: 'app_historique_achat_index', methods: ['GET'])]
    public function index(HistoriqueAchatRepository $historiqueAchatRepository): Response
    {
        return $this->render('module_charge/User/historique_achat/index.html.twig', [
            'historique_achats' => $historiqueAchatRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_historique_achat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $historiqueAchat = new HistoriqueAchat();
        $form = $this->createForm(HistoriqueAchatType::class, $historiqueAchat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($historiqueAchat);
            $entityManager->flush();

            return $this->redirectToRoute('app_historique_achat_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('module_charge//User/historique_achat/new.html.twig', [
            'historique_achat' => $historiqueAchat,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_historique_achat_show', methods: ['GET'])]
    public function show(HistoriqueAchat $historiqueAchat): Response
    {
        return $this->render('/module_charge/User/historique_achat/show.html.twig', [
            'historique_achat' => $historiqueAchat,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_historique_achat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, HistoriqueAchat $historiqueAchat, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HistoriqueAchatType::class, $historiqueAchat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_historique_achat_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('/module_charge/User/historique_achat/edit.html.twig', [
            'historique_achat' => $historiqueAchat,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_historique_achat_delete', methods: ['POST'])]
    public function delete(Request $request, HistoriqueAchat $historiqueAchat, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$historiqueAchat->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($historiqueAchat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_historique_achat_index', [], Response::HTTP_SEE_OTHER);
    }
}
