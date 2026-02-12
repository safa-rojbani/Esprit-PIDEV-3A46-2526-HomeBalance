<?php

namespace App\Controller\ModuleDocuments\FrontOffice;

use App\Entity\Document;
use App\Entity\Gallery;
use App\Entity\User;
use App\Entity\Family;
use App\Enum\EtatDocument;
use App\Form\ModuleDocuments\FrontOffice\DocumentType;
use App\Repository\DocumentRepository;
use App\Repository\GalleryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;

#[Route('/portal/documents')]
final class DocumentController extends AbstractController
{
    #[Route(name: 'app_document_index', methods: ['GET'])]
    public function index(DocumentRepository $documentRepository, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);

        return $this->render('ModuleDocuments/FrontOffice/document/index.html.twig', [
            'documents' => $documentRepository->findBy(['family' => $family]),
        ]);
    }

    #[Route('/new/{galleryId}', name: 'app_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, int $galleryId, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $user = $this->getUser();

        $document = new Document();

        // 🔹 Récupérer la galerie
        $gallery = $entityManager->getRepository(Gallery::class)->find($galleryId);
        if (!$gallery) {
            throw $this->createNotFoundException('Gallery not found');
        }
        $this->assertSameFamily($family, $gallery->getFamily());

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('file')->getData();

            if ($file) {
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->guessExtension();
                $mimeType = $file->getClientMimeType();
                $fileSize = $file->getSize();
                $newFilename = uniqid() . '.' . $extension;

                try {
                    $file->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/documents',
                        $newFilename
                    );
                } catch (FileException $e) {
                    throw $e;
                }

                $document->setFileName($originalName);
                $document->setFilePath('uploads/documents/' . $newFilename);
                $document->setFileType($mimeType);
                $document->setFilesize((string) $fileSize);
                $document->setUploadedAt(new \DateTimeImmutable());
                $document->setCreatedAt(new \DateTimeImmutable());
                $document->setEtat(EtatDocument::ACTIF);

                if (!$user instanceof User) {
                    throw $this->createAccessDeniedException();
                }
                $document->setUploadedBy($user);
                $document->setFamily($family);

                // 🔹 ASSOCIER LA GALERIE
                $document->setGallery($gallery);
            }

            $entityManager->persist($document);
            $entityManager->flush();

            return $this->redirectToRoute('app_gallery_show', ['id' => $galleryId]);
        }

        return $this->render('ModuleDocuments/FrontOffice/document/new.html.twig', [
            'document' => $document,
            'form' => $form,
            'galleryId' => $galleryId,
        ]);
    }

    #[Route('/{id}/{galleryId?}', name: 'app_document_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Document $document, ?int $galleryId = null, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        return $this->render('ModuleDocuments/FrontOffice/document/show.html.twig', [
            'document' => $document,
            'galleryId' => $galleryId, // tu peux l'utiliser pour revenir à la galerie
        ]);
    }


    #[Route('/{id}/edit/{galleryId?}', name: 'app_document_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Document $document,
        EntityManagerInterface $entityManager,
        ?int $galleryId = null,
        ActiveFamilyResolver $familyResolver
    ): Response {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('file')->getData();

            if ($file) {
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->guessExtension();
                $mimeType = $file->getClientMimeType();
                $fileSize = $file->getSize();
                $newFilename = uniqid() . '.' . $extension;

                try {
                    $file->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/documents',
                        $newFilename
                    );
                } catch (FileException $e) {
                    throw $e;
                }

                $document->setFileName($originalName);
                $document->setFilePath('uploads/documents/' . $newFilename);
                $document->setFileType($mimeType);
                $document->setFilesize((string) $fileSize);
                $document->setUploadedAt(new \DateTimeImmutable());
            }

            $document->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            // 🔹 Redirection selon la présence de galleryId
            if ($galleryId) {
                return $this->redirectToRoute('app_gallery_show', ['id' => $galleryId]);
            }

            return $this->redirectToRoute('app_document_index');
        }

        return $this->render('ModuleDocuments/FrontOffice/document/edit.html.twig', [
            'document' => $document,
            'form' => $form,
            'galleryId' => $galleryId,
        ]);
    }



    #[Route('/{id}/delete/{galleryId?}', name: 'app_document_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $entityManager, ?int $galleryId = null, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if ($this->isCsrfTokenValid('delete' . $document->getId(), $request->request->get('_token'))) {
            $document->setEtat(EtatDocument::CORBEILLE);
            $document->setDeletedAt(new \DateTimeImmutable());
            $entityManager->flush();
        }

        if ($galleryId) {
            return $this->redirectToRoute('app_gallery_show', ['id' => $galleryId]);
        }

        return $this->redirectToRoute('app_document_index', [], Response::HTTP_SEE_OTHER);
    }



    #[Route('/document/{id}/hide', name: 'app_document_hide', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function hide(
        Request $request,
        Document $document,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver
    ): Response {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if (!$this->isCsrfTokenValid('hide' . $document->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $document->setEtat(EtatDocument::HIDDEN);
        $document->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        // ✅ on récupère la galerie depuis le document
        $galleryId = $document->getGallery()->getId();

        return $this->redirectToRoute('app_gallery_show', [
            'id' => $galleryId
        ]);
    }


    #[Route('/documents/trash', name: 'app_document_trash', methods: ['GET'])]
    public function trash(DocumentRepository $repo, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);

        $documents = $repo->findBy(
            [
                'etat'   => EtatDocument::CORBEILLE,   // ou CORBEILLE si tu as cet enum
                'family' => $family,
            ]
        );

        return $this->render('ModuleDocuments/FrontOffice/document/trash.html.twig', [
            'documents' => $documents,
        ]);
    }


    #[Route('/documents/trash/restore-all', name: 'app_document_restore_all', methods: ['POST'])]
    public function restoreAll(
        Request $request,
        DocumentRepository $repo,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        if (!$this->isCsrfTokenValid('restore_all', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $family = $this->resolveFamily($familyResolver);

        // 🔹 récupérer tous les documents en corbeille de cette famille
        $documents = $repo->findBy([
            'etat'   => EtatDocument::CORBEILLE,
            'family' => $family,
        ]);

        foreach ($documents as $document) {
            $document->setEtat(EtatDocument::ACTIF);
            $document->setDeletedAt(null);
        }

        $em->flush();

        return $this->redirectToRoute('app_document_trash');
    }


    #[Route('/documents/{id}/restore', name: 'app_document_restore', methods: ['POST'])]
    public function restore(Request $request, Document $document, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if (!$this->isCsrfTokenValid('restore' . $document->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $document->setEtat(EtatDocument::ACTIF);
        $document->setDeletedAt(null);

        $em->flush();

        return $this->redirectToRoute('app_document_trash');
    }

    #[Route('/documents/{id}/delete-permanently', name: 'app_document_delete_permanently', methods: ['POST'])]
    public function deletePermanently(Request $request, Document $document, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if (!$this->isCsrfTokenValid('delete_permanently' . $document->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $document->setEtat(EtatDocument::DELETED);
        $document->setDeletedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->redirectToRoute('app_document_trash');
    }



    #[Route('/documents/trash/delete-all-permanently', name: 'app_document_delete_all_permanently', methods: ['POST'])]
    public function deleteAllPermanently(Request $request, DocumentRepository $repo, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        if (!$this->isCsrfTokenValid('delete_all_permanently', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $family = $this->resolveFamily($familyResolver);

        $documents = $repo->findBy([
            'etat'   => EtatDocument::CORBEILLE,
            'family' => $family,
        ]);

        $now = new \DateTimeImmutable();

        foreach ($documents as $doc) {
            $doc->setEtat(EtatDocument::DELETED); // لازم تكون موجودة في enum
            $doc->setDeletedAt($now);
        }

        $em->flush();

        return $this->redirectToRoute('app_document_trash');
    }


    #[Route('/documents/{id}/transfer', name: 'app_document_transfer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function transfer(
        Request $request,
        Document $document,
        GalleryRepository $galleryRepository,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());
        // ✅ CSRF
        if (!$this->isCsrfTokenValid('transfer' . $document->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // ✅ Security: same family
        $targetId = (int) $request->request->get('target_gallery_id');
        if (!$targetId) {
            $this->addFlash('danger', 'Please choose a target gallery.');
            return $this->redirectToRoute('app_gallery_show', ['id' => $document->getGallery()->getId()]);
        }

        $targetGallery = $galleryRepository->find($targetId);
        if (!$targetGallery) {
            throw $this->createNotFoundException('Target gallery not found.');
        }

        // ✅ Security: target gallery same family
        if (!$targetGallery->getFamily() || $targetGallery->getFamily()->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        // ✅ Transfer
        $document->setGallery($targetGallery);
        $document->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Document transferred ✅');

        // ✅ Redirect to target gallery
        return $this->redirectToRoute('app_gallery_show', ['id' => $targetGallery->getId()]);
    }


    #[Route('/documents/{id}/restore', name: 'app_document_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
public function restoreHidden(Request $request, Document $document, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
{
    $family = $this->resolveFamily($familyResolver);
    $this->assertSameFamily($family, $document->getFamily());

    if (!$this->isCsrfTokenValid('restore' . $document->getId(), $request->request->get('_token'))) {
        throw $this->createAccessDeniedException();
    }

    // (optionnel) sécurité famille
    if ($document->getEtat() === EtatDocument::HIDDEN) {
        $document->setEtat(EtatDocument::ACTIF);
        $document->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();
    }

    $this->addFlash('success', 'Document restored successfully.');
    return $this->redirectToRoute('app_gallery_hidden');
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
