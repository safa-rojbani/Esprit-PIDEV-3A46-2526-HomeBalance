<?php

namespace App\Controller\ModuleDocuments\BackOffice;

use App\Entity\DefaultGallery;
use App\Form\ModuleDocuments\BackOffice\DefaultGalleryType;
use App\Repository\DefaultGalleryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/documents/default-galleries')]
final class DefaultGalleryController extends AbstractController
{
    #[Route(name: 'app_default_gallery_index', methods: ['GET'])]
    public function index(DefaultGalleryRepository $defaultGalleryRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('ModuleDocuments/BackOffice/default_gallery/index.html.twig', [
            'default_galleries' => $defaultGalleryRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_default_gallery_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $defaultGallery = new DefaultGallery();
        $form = $this->createForm(DefaultGalleryType::class, $defaultGallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($defaultGallery);
            $entityManager->flush();

            return $this->redirectToRoute('app_default_gallery_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ModuleDocuments/BackOffice/default_gallery/new.html.twig', [
            'default_gallery' => $defaultGallery,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_default_gallery_show', methods: ['GET'])]
    public function show(DefaultGallery $defaultGallery): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('ModuleDocuments/BackOffice/default_gallery/show.html.twig', [
            'default_gallery' => $defaultGallery,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_default_gallery_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DefaultGallery $defaultGallery, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(DefaultGalleryType::class, $defaultGallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_default_gallery_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ModuleDocuments/BackOffice/default_gallery/edit.html.twig', [
            'default_gallery' => $defaultGallery,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_default_gallery_delete', methods: ['POST'])]
    public function delete(Request $request, DefaultGallery $defaultGallery, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$defaultGallery->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($defaultGallery);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_default_gallery_index', [], Response::HTTP_SEE_OTHER);
    }
}
