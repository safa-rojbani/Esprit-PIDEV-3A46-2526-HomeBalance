<?php

namespace App\Controller\ModuleDocuments\FrontOffice;

use App\Entity\Gallery;
use App\Entity\User;
use App\Enum\EtatDocument;
use App\Enum\EtatGallery;
use App\Form\ModuleDocuments\FrontOffice\GalleryType;
use App\Repository\DocumentRepository;
use App\Repository\GalleryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gallery')]
final class GalleryController extends AbstractController
{
    private UserRepository $userRepository;
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }



    #[Route(name: 'app_gallery_index', methods: ['GET'])]
    public function index(GalleryRepository $galleryRepository): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            $user = $this->userRepository->find(1);
        }


        $family = $user->getFamily();

        if (!$family) {
            return $this->render('ModuleDocuments/FrontOffice/gallery/index.html.twig', [
                'galleries' => [],
            ]);
        }

        $galleries = $galleryRepository->findBy(
            ['family' => $family]
        );

        return $this->render('ModuleDocuments/FrontOffice/gallery/index.html.twig', [
            'galleries' => $galleries,
        ]);
    }


    #[Route('/new', name: 'app_gallery_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $gallery = new Gallery();
        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        // ✅ If user chose a template, always copy name/description from it (before validation)
        if ($form->isSubmitted()) {
            $tpl = $gallery->getDefaultGallery();

            if ($tpl) {
                $gallery->setName($tpl->getName());
                $gallery->setDescription($tpl->getDescription()); // can be null, it's ok
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ Always fill all mandatory fields (both cases)
            $gallery->setCreatedAt(new \DateTimeImmutable());
            $gallery->setUpdatedAt(null);
            $gallery->setDeletedAt(null);
            $gallery->setEtat(\App\Enum\EtatGallery::ACTIF);

            $user = $this->getUser() ?: $this->userRepository->find(1);
            $gallery->setCreatedBy($user);
            $gallery->setFamily($user->getFamily());

            $entityManager->persist($gallery);
            $entityManager->flush();

            return $this->redirectToRoute('app_gallery_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ModuleDocuments/FrontOffice/gallery/new.html.twig', [
            'gallery' => $gallery,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gallery_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, Gallery $gallery, GalleryRepository $galleryRepository): Response
    {
        $from = $request->query->get('from', 'index');

        $user = $this->getUser();
        if (!$user) {
            $user = $this->userRepository->find(1);
        }

        $family = $user->getFamily();

        // ✅ galleries de la même famille
        $familyGalleries = $galleryRepository->findBy(['family' => $family]);

        return $this->render('ModuleDocuments/FrontOffice/gallery/show.html.twig', [
            'gallery' => $gallery,
            'documents' => $gallery->getDocuments(),
            'from' => $from,
            'familyGalleries' => $familyGalleries,
        ]);
    }


    #[Route('/{id}/edit', name: 'app_gallery_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Gallery $gallery, EntityManagerInterface $entityManager): Response
    {
        $from = $request->query->get('from', 'index');

        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $gallery->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            return $this->redirectToRoute(
                $from === 'hidden' ? 'app_gallery_hidden' : 'app_gallery_index',
                [],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->render('ModuleDocuments/FrontOffice/gallery/edit.html.twig', [
            'gallery' => $gallery,
            'form' => $form,
            'from' => $from,
        ]);
    }


    #[Route('/{id}', name: 'app_gallery_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Gallery $gallery, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $gallery->getId(), $request->getPayload()->getString('_token'))) {
            $gallery->setDeletedAt(new \DateTimeImmutable());
            $gallery->setEtat(\App\Enum\EtatGallery::DELETED);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gallery_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/hide', name: 'app_gallery_hide', methods: ['POST'])]
    public function hide(Request $request, Gallery $gallery, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('hide' . $gallery->getId(), $request->request->get('_token'))) {
            $gallery->setEtat(\App\Enum\EtatGallery::HIDDEN);


            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gallery_index');
    }
    #[Route('/hidden', name: 'app_gallery_hidden', methods: ['GET'])]
    public function hidden(
        GalleryRepository $galleryRepository,
        DocumentRepository $documentRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            $user = $em->getRepository(User::class)->find(1);
        }

        $family = $user->getFamily();

        // ✅ Hidden galleries (de la famille)
        $galleries = $galleryRepository->findBy([
            'etat'   => EtatGallery::HIDDEN,
            'family' => $family,
        ]);

        // ✅ Hidden documents (de la famille) — même si la gallery est active
        $documents = $documentRepository->findBy([
            'etat'   => EtatDocument::HIDDEN,
            'family' => $family,
        ]);

        return $this->render('ModuleDocuments/FrontOffice/gallery/hidden.html.twig', [
            'galleries' => $galleries,
            'documents' => $documents,
        ]);
    }


    #[Route('/{id}/activate', name: 'app_gallery_activate', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function activate(Gallery $gallery, EntityManagerInterface $em): Response
    {
        // Vérifie que la gallery est bien HIDDEN
        if ($gallery->getEtat() === EtatGallery::HIDDEN) {
            $gallery->setEtat(EtatGallery::ACTIF);
            $em->flush();
        }

        $this->addFlash('success', 'Gallery activated successfully.');

        return $this->redirectToRoute('app_gallery_hidden');
    }
}
