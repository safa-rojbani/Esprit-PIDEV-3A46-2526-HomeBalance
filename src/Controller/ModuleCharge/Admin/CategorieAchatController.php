<?php

namespace App\Controller\ModuleCharge\Admin;


use App\Entity\CategorieAchat;
use App\Entity\Family;
use App\Entity\User;
use App\Form\ModuleCharge\CategorieAchatType;
use App\Repository\CategorieAchatRepository;
use App\ServiceModuleCharge\CategorieAchatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;

#[Route('/portal/charge/categories-achat')]
final class CategorieAchatController extends AbstractController
{
    #[Route(name: 'app_categorie_achat_index', methods: ['GET'])]
    public function index(CategorieAchatService $Service, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);

        return $this->render('module_charge/Admin/categorie_achat/index.html.twig', [
            'categorie_achats' => $Service->findAllByFamily($family),
        ]);
    }

    #[Route('/new', name: 'app_categorie_achat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
          
        $categorieAchat = new CategorieAchat();

        

        $form = $this->createForm(CategorieAchatType::class, $categorieAchat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $categorieAchat->setFamily($family);
            // Utilisation du service pour créer la catégorie
            #$categorie = $Service->create($categorieAchat);
            $entityManager->persist($categorieAchat);
            $entityManager->flush();

            return $this->redirectToRoute('app_categorie_achat_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('module_charge/Admin/categorie_achat/new.html.twig', [
            'categorie_achat' => $categorieAchat,
            'form' => $form,
            
        ]);
    }

    #[Route('/{id}', name: 'app_categorie_achat_show', methods: ['GET'])]
    public function show(CategorieAchat $categorieAchat, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $categorieAchat->getFamily());

        return $this->render('module_charge/Admin/categorie_achat/show.html.twig', [
            'categorie_achat' => $categorieAchat,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_categorie_achat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CategorieAchat $categorieAchat,  EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $categorieAchat->getFamily());

        $form = $this->createForm(CategorieAchatType::class, $categorieAchat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_categorie_achat_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('module_charge/Admin/categorie_achat/edit.html.twig', [
            'categorie_achat' => $categorieAchat,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_categorie_achat_delete', methods: ['POST'])]
    public function delete(Request $request, CategorieAchat $categorieAchat, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $categorieAchat->getFamily());

        if ($this->isCsrfTokenValid('delete'.$categorieAchat->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($categorieAchat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_categorie_achat_index', [], Response::HTTP_SEE_OTHER);
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
