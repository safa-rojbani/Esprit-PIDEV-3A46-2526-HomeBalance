<?php

namespace App\Controller\ModuleCharge\User;

use App\Entity\HistoriqueAchat;
use App\Entity\Family;
use App\Entity\User;
use App\Form\ModuleCharge\HistoriqueAchatType;
use App\Repository\HistoriqueAchatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;

#[Route('/portal/charge/historique/achats')]
final class HistoriqueAchatController extends AbstractController
{
    #[Route(name: 'app_historique_achat_index', methods: ['GET'])]
    public function index(Request $request, HistoriqueAchatRepository $historiqueAchatRepository, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $searchQuery = trim((string) $request->query->get('q', ''));

        return $this->render('module_charge/User/historique_achat/index.html.twig', [
            'historique_achats' => $historiqueAchatRepository->searchByFamily($family, $searchQuery),
            'searchQuery' => $searchQuery,
        ]);
    }

    #[Route('/new', name: 'app_historique_achat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);

        $historiqueAchat = new HistoriqueAchat();
        $form = $this->createForm(HistoriqueAchatType::class, $historiqueAchat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $historiqueAchat->setFamily($family);
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
    public function show(HistoriqueAchat $historiqueAchat, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $historiqueAchat->getFamily());

        return $this->render('/module_charge/User/historique_achat/show.html.twig', [
            'historique_achat' => $historiqueAchat,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_historique_achat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, HistoriqueAchat $historiqueAchat, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $historiqueAchat->getFamily());

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
    public function delete(Request $request, HistoriqueAchat $historiqueAchat, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $historiqueAchat->getFamily());

        if ($this->isCsrfTokenValid('delete'.$historiqueAchat->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($historiqueAchat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_historique_achat_index', [], Response::HTTP_SEE_OTHER);
    }

    private function resolveFamily(ActiveFamilyResolver $familyResolver): Family
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $family = $familyResolver->resolveForUser($user);
        if ($family === null) {
            throw $this->createAccessDeniedException();
        }

        return $family;
    }

    private function assertSameFamily(Family $family, ?Family $targetFamily): void
    {
        if ($targetFamily === null || $targetFamily->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
