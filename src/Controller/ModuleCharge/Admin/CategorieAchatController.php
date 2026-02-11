<?php

namespace App\Controller\ModuleCharge\Admin;


use App\Entity\CategorieAchat;
use App\Form\CategorieAchatType;
use App\Repository\CategorieAchatRepository;
use App\ServiceModuleCharge\CategorieAchatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/categorie/achat')]
final class CategorieAchatController extends AbstractController
{
    #[Route(name: 'app_categorie_achat_index', methods: ['GET'])]
    public function index(CategorieAchatService $Service): Response
    {
        return $this->render('module_charge/Admin/categorie_achat/index.html.twig', [
            'categorie_achats' => $Service->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_categorie_achat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
          
        $categorieAchat = new CategorieAchat();

        

        $form = $this->createForm(CategorieAchatType::class, $categorieAchat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
    public function show(CategorieAchat $categorieAchat): Response
    {
        return $this->render('module_charge/Admin/categorie_achat/show.html.twig', [
            'categorie_achat' => $categorieAchat,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_categorie_achat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CategorieAchat $categorieAchat,  EntityManagerInterface $entityManager): Response
    {
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
    public function delete(Request $request, CategorieAchat $categorieAchat, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$categorieAchat->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($categorieAchat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_categorie_achat_index', [], Response::HTTP_SEE_OTHER);
    }
}
