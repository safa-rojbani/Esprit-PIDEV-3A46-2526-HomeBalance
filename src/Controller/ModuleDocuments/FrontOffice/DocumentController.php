<?php

namespace App\Controller\ModuleDocuments\FrontOffice;

use App\Entity\Document;
use App\Entity\Family;
use App\Entity\Gallery;
use App\Entity\User;
use App\Enum\DocumentActivityEvent;
use App\Enum\EtatDocument;
use App\Form\ModuleDocuments\FrontOffice\DocumentType;
use App\Repository\DocumentRepository;
use App\Repository\GalleryRepository;
use App\Service\ActiveFamilyResolver;
use App\Service\DocumentActivityTracker;
use App\Service\PortalNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/documents')]
final class DocumentController extends AbstractController
{
    #[Route(name: 'app_document_index', methods: ['GET'])]
    public function index(
        Request $request,
        DocumentRepository $documentRepository,
        ActiveFamilyResolver $familyResolver,
        PaginatorInterface $paginator
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $query = $documentRepository->createQueryBuilder('d')
            ->andWhere('d.family = :family')
            ->setParameter('family', $family)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery();

        $documents = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            6
        );

        return $this->render('ModuleDocuments/FrontOffice/document/index.html.twig', [
            'documents' => $documents,
        ]);
    }

    #[Route('/new/{galleryId}', name: 'app_document_new', methods: ['GET', 'POST'], requirements: ['galleryId' => '\d+'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        int $galleryId,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService,
        DocumentActivityTracker $documentActivityTracker
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $user = $this->getUser();

        $document = new Document();
        $gallery = $entityManager->getRepository(Gallery::class)->find($galleryId);
        if (!$gallery) {
            throw $this->createNotFoundException('Gallery not found');
        }
        $this->assertSameFamily($family, $gallery->getFamily());
        $document->setFamily($family);
        $document->setGallery($gallery);

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $document->getFile() instanceof UploadedFile) {
            $originalName = trim((string) pathinfo($document->getFile()->getClientOriginalName(), PATHINFO_FILENAME));
            $document->setFileName($originalName !== '' ? $originalName : 'document');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $document->getFile();
            if ($file) {
                $this->persistFile($document, $file);

                if (!$user instanceof User) {
                    throw $this->createAccessDeniedException();
                }
                $document->setUploadedBY($user);
                $document->setFamily($family);
                $document->setGallery($gallery);
                $document->setEtat(EtatDocument::ACTIF);
                $document->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($document);
                $entityManager->flush();

                $portalNotificationService->notifyFamily($family, $user, 'document_uploaded', [
                    'documentId' => $document->getId(),
                    'documentName' => $document->getFileName(),
                    'galleryId' => $gallery->getId(),
                    'galleryName' => $gallery->getName(),
                    'route' => 'app_gallery_show',
                    'routeParams' => ['id' => $gallery->getId()],
                ]);
                $documentActivityTracker->track(
                    $family,
                    $user,
                    $document,
                    DocumentActivityEvent::DOCUMENT_UPLOADED,
                    null,
                    [
                        'source' => 'form',
                        'galleryId' => $gallery->getId(),
                    ]
                );
                $entityManager->flush();

                return $this->redirectToRoute('app_gallery_show', ['id' => $galleryId]);
            }
        }

        return $this->render('ModuleDocuments/FrontOffice/document/new.html.twig', [
            'document' => $document,
            'form' => $form,
            'galleryId' => $galleryId,
        ]);
    }

    #[Route('/{id}/{galleryId}', name: 'app_document_show', methods: ['GET'], requirements: ['id' => '\d+', 'galleryId' => '\d+'], defaults: ['galleryId' => null])]
    public function show(
        Request $request,
        Document $document,
        ActiveFamilyResolver $familyResolver,
        DocumentActivityTracker $documentActivityTracker,
        EntityManagerInterface $entityManager,
        ?int $galleryId = null
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if ($galleryId === null && $document->getGallery() !== null) {
            $galleryId = $document->getGallery()->getId();
        }

        $actor = $this->getUser();
        $documentActivityTracker->track(
            $family,
            $actor instanceof User ? $actor : null,
            $document,
            DocumentActivityEvent::DOCUMENT_VIEWED,
            null,
            [
                'route' => 'app_document_show',
            ]
        );
        $entityManager->flush();

        $shareResult = null;
        $user = $this->getUser();
        if ($user instanceof User && $user->getId() !== null && $document->getId() !== null) {
            $sessionKey = sprintf('document_share_result_%s_%d', $user->getId(), $document->getId());
            $session = $request->getSession();

            if ($session->has($sessionKey)) {
                $stored = $session->get($sessionKey);
                $session->remove($sessionKey);

                if (is_array($stored)) {
                    $shareResult = $stored;
                }
            }
        }

        return $this->render('ModuleDocuments/FrontOffice/document/show.html.twig', [
            'document' => $document,
            'galleryId' => $galleryId,
            'share_result' => $shareResult,
        ]);
    }

    #[Route('/{id}/edit/{galleryId}', name: 'app_document_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+', 'galleryId' => '\d+'], defaults: ['galleryId' => null])]
    public function edit(
        Request $request,
        Document $document,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService,
        ?int $galleryId = null
    ): Response {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if ($galleryId === null && $document->getGallery() !== null) {
            $galleryId = $document->getGallery()->getId();
        }

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $document->getFile() instanceof UploadedFile) {
            $originalName = trim((string) pathinfo($document->getFile()->getClientOriginalName(), PATHINFO_FILENAME));
            $document->setFileName($originalName !== '' ? $originalName : 'document');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $actor = $this->resolveActor();
            /** @var UploadedFile|null $file */
            $file = $document->getFile();
            if ($file) {
                $this->persistFile($document, $file);
            }

            $document->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $targetRoute = 'app_document_index';
            $targetRouteParams = [];
            if ($document->getGallery() !== null && $document->getGallery()->getId() !== null) {
                $targetRoute = 'app_gallery_show';
                $targetRouteParams = ['id' => $document->getGallery()->getId()];
            }

            $portalNotificationService->notifyFamily($family, $actor, 'document_updated', [
                'documentId' => $document->getId(),
                'documentName' => $document->getFileName(),
                'galleryId' => $document->getGallery()?->getId(),
                'galleryName' => $document->getGallery()?->getName(),
                'route' => $targetRoute,
                'routeParams' => $targetRouteParams,
            ]);

            if ($galleryId !== null) {
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

    #[Route('/{id}/delete/{galleryId}', name: 'app_document_delete', methods: ['POST'], requirements: ['id' => '\d+', 'galleryId' => '\d+'], defaults: ['galleryId' => null])]
    public function delete(
        Request $request,
        Document $document,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService,
        ?int $galleryId = null
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if ($this->isCsrfTokenValid('delete' . $document->getId(), (string) $request->request->get('_token'))) {
            $actor = $this->resolveActor();
            $document->setEtat(EtatDocument::CORBEILLE);
            $document->setDeletedAt(new \DateTimeImmutable());
            $document->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $portalNotificationService->notifyFamily($family, $actor, 'document_deleted_to_trash', [
                'documentId' => $document->getId(),
                'documentName' => $document->getFileName(),
                'galleryId' => $document->getGallery()?->getId(),
                'galleryName' => $document->getGallery()?->getName(),
                'route' => 'app_document_trash',
                'routeParams' => [],
            ]);
        }

        if ($galleryId !== null) {
            return $this->redirectToRoute('app_gallery_show', ['id' => $galleryId]);
        }

        return $this->redirectToRoute('app_document_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/document/{id}/hide', name: 'app_document_hide', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function hide(
        Request $request,
        Document $document,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if (!$this->isCsrfTokenValid('hide' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $actor = $this->resolveActor();
        $document->setEtat(EtatDocument::HIDDEN);
        $document->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $portalNotificationService->notifyFamily($family, $actor, 'document_hidden', [
            'documentId' => $document->getId(),
            'documentName' => $document->getFileName(),
            'galleryId' => $document->getGallery()?->getId(),
            'galleryName' => $document->getGallery()?->getName(),
            'route' => 'app_gallery_hidden',
            'routeParams' => [],
        ]);

        return $this->redirectToRoute('app_gallery_show', [
            'id' => $document->getGallery()->getId(),
        ]);
    }

    #[Route('/documents/trash', name: 'app_document_trash', methods: ['GET'])]
    public function trash(
        Request $request,
        DocumentRepository $repo,
        ActiveFamilyResolver $familyResolver,
        PaginatorInterface $paginator
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $query = $repo->createQueryBuilder('d')
            ->andWhere('d.etat = :etat')
            ->andWhere('d.family = :family')
            ->setParameter('etat', EtatDocument::CORBEILLE->value)
            ->setParameter('family', $family)
            ->orderBy('d.deletedAt', 'DESC')
            ->getQuery();

        $documents = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            6
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
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService
    ): Response {
        if (!$this->isCsrfTokenValid('restore_all', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $actor = $this->resolveActor();
        $family = $this->resolveFamily($familyResolver);
        $documents = $repo->findBy([
            'etat' => EtatDocument::CORBEILLE,
            'family' => $family,
        ]);

        $restoredCount = \count($documents);

        foreach ($documents as $document) {
            $document->setEtat(EtatDocument::ACTIF);
            $document->setDeletedAt(null);
            $document->setUpdatedAt(new \DateTimeImmutable());
        }

        $em->flush();

        if ($restoredCount > 0) {
            $portalNotificationService->notifyFamily($family, $actor, 'documents_restored_from_trash', [
                'count' => $restoredCount,
                'route' => 'app_document_trash',
                'routeParams' => [],
            ]);
        }

        return $this->redirectToRoute('app_document_trash');
    }

    #[Route('/documents/{id}/restore', name: 'app_document_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(
        Request $request,
        Document $document,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService
    ): Response
    {
        $actor = $this->resolveActor();
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if (!$this->isCsrfTokenValid('restore' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $wasHidden = $document->getEtat() === EtatDocument::HIDDEN;

        $document->setEtat(EtatDocument::ACTIF);
        $document->setDeletedAt(null);
        $document->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $portalNotificationService->notifyFamily(
            $family,
            $actor,
            $wasHidden ? 'document_restored_from_hidden' : 'document_restored_from_trash',
            [
                'documentId' => $document->getId(),
                'documentName' => $document->getFileName(),
                'galleryId' => $document->getGallery()?->getId(),
                'galleryName' => $document->getGallery()?->getName(),
                'route' => $wasHidden ? 'app_gallery_hidden' : 'app_document_trash',
                'routeParams' => [],
            ]
        );

        if ($wasHidden) {
            $this->addFlash('success', 'Document restored successfully.');
            return $this->redirectToRoute('app_gallery_hidden');
        }

        return $this->redirectToRoute('app_document_trash');
    }

    #[Route('/documents/{id}/delete-permanently', name: 'app_document_delete_permanently', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deletePermanently(
        Request $request,
        Document $document,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService
    ): Response
    {
        $actor = $this->resolveActor();
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if (!$this->isCsrfTokenValid('delete_permanently' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $document->setEtat(EtatDocument::DELETED);
        $document->setDeletedAt(new \DateTimeImmutable());
        $document->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $portalNotificationService->notifyFamily($family, $actor, 'document_deleted_permanently', [
            'documentId' => $document->getId(),
            'documentName' => $document->getFileName(),
            'galleryId' => $document->getGallery()?->getId(),
            'galleryName' => $document->getGallery()?->getName(),
            'route' => 'app_document_trash',
            'routeParams' => [],
        ]);

        return $this->redirectToRoute('app_document_trash');
    }

    #[Route('/documents/trash/delete-all-permanently', name: 'app_document_delete_all_permanently', methods: ['POST'])]
    public function deleteAllPermanently(
        Request $request,
        DocumentRepository $repo,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService
    ): Response
    {
        if (!$this->isCsrfTokenValid('delete_all_permanently', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $actor = $this->resolveActor();
        $family = $this->resolveFamily($familyResolver);
        $documents = $repo->findBy([
            'etat' => EtatDocument::CORBEILLE,
            'family' => $family,
        ]);

        $deletedCount = \count($documents);

        $now = new \DateTimeImmutable();
        foreach ($documents as $doc) {
            $doc->setEtat(EtatDocument::DELETED);
            $doc->setDeletedAt($now);
            $doc->setUpdatedAt($now);
        }

        $em->flush();

        if ($deletedCount > 0) {
            $portalNotificationService->notifyFamily($family, $actor, 'documents_deleted_permanently', [
                'count' => $deletedCount,
                'route' => 'app_document_trash',
                'routeParams' => [],
            ]);
        }

        return $this->redirectToRoute('app_document_trash');
    }

    #[Route('/documents/{id}/transfer', name: 'app_document_transfer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function transfer(
        Request $request,
        Document $document,
        GalleryRepository $galleryRepository,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService
    ): Response {
        $actor = $this->resolveActor();
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());

        if (!$this->isCsrfTokenValid('transfer' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $targetId = (int) $request->request->get('target_gallery_id');
        if (!$targetId) {
            $this->addFlash('danger', 'Please choose a target gallery.');
            return $this->redirectToRoute('app_gallery_show', ['id' => $document->getGallery()->getId()]);
        }

        $targetGallery = $galleryRepository->find($targetId);
        if (!$targetGallery) {
            throw $this->createNotFoundException('Target gallery not found.');
        }

        if (!$targetGallery->getFamily() || $targetGallery->getFamily()->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        $sourceGalleryName = $document->getGallery()?->getName();
        $document->setGallery($targetGallery);
        $document->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $portalNotificationService->notifyFamily($family, $actor, 'document_transferred', [
            'documentId' => $document->getId(),
            'documentName' => $document->getFileName(),
            'fromGalleryName' => $sourceGalleryName,
            'toGalleryId' => $targetGallery->getId(),
            'toGalleryName' => $targetGallery->getName(),
            'route' => 'app_gallery_show',
            'routeParams' => ['id' => $targetGallery->getId()],
        ]);

        $this->addFlash('success', 'Document transferred.');

        return $this->redirectToRoute('app_gallery_show', ['id' => $targetGallery->getId()]);
    }

    private function persistFile(Document $document, UploadedFile $file): void
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->guessExtension() ?: 'bin';
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';
        $fileSize = $file->getSize() ?? 0;
        $newFilename = uniqid('', true) . '.' . $extension;

        try {
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/documents',
                $newFilename
            );
        } catch (FileException $e) {
            throw $e;
        }

        $ext = strtolower((string) $extension);
        if ($mimeType === 'application/octet-stream') {
            $mimeMap = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'mov' => 'video/quicktime',
                'mkv' => 'video/x-matroska',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'txt' => 'text/plain',
                'zip' => 'application/zip',
            ];
            if (isset($mimeMap[$ext])) {
                $mimeType = $mimeMap[$ext];
            }
        }

        $document->setFileName($originalName);
        $document->setFilePath('uploads/documents/' . $newFilename);
        $document->setFileType($mimeType);
        $document->setFilesize((string) $fileSize);
        $document->setUploadedAt(new \DateTimeImmutable());
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

    private function resolveActor(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function assertSameFamily(Family $family, ?Family $targetFamily): void
    {
        if ($targetFamily === null || $targetFamily->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
