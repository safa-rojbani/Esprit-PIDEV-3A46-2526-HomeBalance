<?php

namespace App\Controller\ModuleDocuments\FrontOffice;

use App\Entity\Family;
use App\Entity\Gallery;
use App\Entity\User;
use App\Enum\EtatDocument;
use App\Enum\EtatGallery;
use App\Form\ModuleDocuments\FrontOffice\GalleryType;
use App\Repository\DocumentRepository;
use App\Repository\GalleryRepository;
use App\Service\ActiveFamilyResolver;
use App\Service\PortalNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/documents/galleries')]
final class GalleryController extends AbstractController
{
    #[Route(name: 'app_gallery_index', methods: ['GET'])]
    public function index(
        Request $request,
        GalleryRepository $galleryRepository,
        ActiveFamilyResolver $familyResolver,
        PaginatorInterface $paginator
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $query = $galleryRepository->createQueryBuilder('g')
            ->andWhere('g.family = :family')
            ->andWhere('g.etat = :etat')
            ->setParameter('family', $family)
            ->setParameter('etat', EtatGallery::ACTIF->value)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery();

        $galleries = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            8
        );

        return $this->render('ModuleDocuments/FrontOffice/gallery/index.html.twig', [
            'galleries' => $galleries,
        ]);
    }

    #[Route('/new', name: 'app_gallery_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        GalleryRepository $galleryRepository,
        PortalNotificationService $portalNotificationService
    ): Response {
        $family = $this->resolveFamily($familyResolver);
        $user = $this->getUser();

        $gallery = new Gallery();
        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $gallery->setFamily($family);

            $tpl = $gallery->getDefaultGallery();
            if ($tpl) {
                $gallery->setName($tpl->getName());
                $gallery->setDescription($tpl->getDescription());
            }

            if ($gallery->getName() !== null) {
                $gallery->setName(trim($gallery->getName()));
            }

            if ($this->galleryNameExistsInFamily($galleryRepository, $family, $gallery->getName())) {
                $form->addError(new FormError('Ce nom de galerie existe deja dans votre famille.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }

            $gallery->setCreatedAt(new \DateTimeImmutable());
            $gallery->setUpdatedAt(null);
            $gallery->setDeletedAt(null);
            $gallery->setEtat(EtatGallery::ACTIF);
            $gallery->setCreatedBy($user);
            $gallery->setFamily($family);

            $entityManager->persist($gallery);
            $entityManager->flush();

            $portalNotificationService->notifyFamily($family, $user, 'gallery_created', [
                'galleryId' => $gallery->getId(),
                'galleryName' => $gallery->getName(),
                'route' => 'app_gallery_show',
                'routeParams' => ['id' => $gallery->getId()],
            ]);

            return $this->redirectToRoute('app_gallery_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ModuleDocuments/FrontOffice/gallery/new.html.twig', [
            'gallery' => $gallery,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gallery_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Request $request,
        Gallery $gallery,
        GalleryRepository $galleryRepository,
        DocumentRepository $documentRepository,
        ActiveFamilyResolver $familyResolver,
        PaginatorInterface $paginator
    ): Response {
        $from = $request->query->get('from', 'index');

        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $gallery->getFamily());

        $documentsQuery = $documentRepository->createQueryBuilder('d')
            ->andWhere('d.gallery = :gallery')
            ->andWhere('d.family = :family')
            ->andWhere('d.etat = :etat')
            ->setParameter('gallery', $gallery)
            ->setParameter('family', $family)
            ->setParameter('etat', EtatDocument::ACTIF->value)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery();

        $documents = $paginator->paginate(
            $documentsQuery,
            $request->query->getInt('page', 1),
            6
        );

        return $this->render('ModuleDocuments/FrontOffice/gallery/show.html.twig', [
            'gallery' => $gallery,
            'documents' => $documents,
            'from' => $from,
            'familyGalleries' => $galleryRepository->findBy(['family' => $family]),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gallery_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Gallery $gallery,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        GalleryRepository $galleryRepository,
        PortalNotificationService $portalNotificationService
    ): Response {
        $from = $request->query->get('from', 'index');

        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $gallery->getFamily());

        $form = $this->createForm(GalleryType::class, $gallery);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $gallery->getName() !== null) {
            $gallery->setName(trim($gallery->getName()));

            if ($this->galleryNameExistsInFamily($galleryRepository, $family, $gallery->getName(), $gallery->getId())) {
                $form->addError(new FormError('Ce nom de galerie existe deja dans votre famille.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $actor = $this->resolveActor();
            $gallery->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $portalNotificationService->notifyFamily($family, $actor, 'gallery_updated', [
                'galleryId' => $gallery->getId(),
                'galleryName' => $gallery->getName(),
                'route' => 'app_gallery_show',
                'routeParams' => ['id' => $gallery->getId()],
            ]);

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
    public function delete(
        Request $request,
        Gallery $gallery,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $gallery->getFamily());

        if ($this->isCsrfTokenValid('delete' . $gallery->getId(), $request->getPayload()->getString('_token'))) {
            $actor = $this->resolveActor();
            $gallery->setDeletedAt(new \DateTimeImmutable());
            $gallery->setEtat(EtatGallery::DELETED);
            $entityManager->flush();

            $portalNotificationService->notifyFamily($family, $actor, 'gallery_deleted', [
                'galleryId' => $gallery->getId(),
                'galleryName' => $gallery->getName(),
                'route' => 'app_gallery_index',
                'routeParams' => [],
            ]);
        }

        return $this->redirectToRoute('app_gallery_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/hide', name: 'app_gallery_hide', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function hide(
        Request $request,
        Gallery $gallery,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $gallery->getFamily());

        if ($this->isCsrfTokenValid('hide' . $gallery->getId(), (string) $request->request->get('_token'))) {
            $actor = $this->resolveActor();
            $gallery->setEtat(EtatGallery::HIDDEN);
            $gallery->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $portalNotificationService->notifyFamily($family, $actor, 'gallery_hidden', [
                'galleryId' => $gallery->getId(),
                'galleryName' => $gallery->getName(),
                'route' => 'app_gallery_hidden',
                'routeParams' => [],
            ]);
        }

        return $this->redirectToRoute('app_gallery_index');
    }

    #[Route('/hidden', name: 'app_gallery_hidden', methods: ['GET'])]
    public function hidden(
        Request $request,
        GalleryRepository $galleryRepository,
        DocumentRepository $documentRepository,
        ActiveFamilyResolver $familyResolver,
        PaginatorInterface $paginator
    ): Response {
        $family = $this->resolveFamily($familyResolver);
        $galleriesQuery = $galleryRepository->createQueryBuilder('g')
            ->andWhere('g.etat = :etat')
            ->andWhere('g.family = :family')
            ->setParameter('etat', EtatGallery::HIDDEN->value)
            ->setParameter('family', $family)
            ->orderBy('g.updatedAt', 'DESC')
            ->getQuery();

        $documentsQuery = $documentRepository->createQueryBuilder('d')
            ->andWhere('d.etat = :etat')
            ->andWhere('d.family = :family')
            ->setParameter('etat', EtatDocument::HIDDEN->value)
            ->setParameter('family', $family)
            ->orderBy('d.updatedAt', 'DESC')
            ->getQuery();

        $galleries = $paginator->paginate(
            $galleriesQuery,
            $request->query->getInt('g_page', 1),
            6,
            ['pageParameterName' => 'g_page']
        );

        $documents = $paginator->paginate(
            $documentsQuery,
            $request->query->getInt('d_page', 1),
            6,
            ['pageParameterName' => 'd_page']
        );

        return $this->render('ModuleDocuments/FrontOffice/gallery/hidden.html.twig', [
            'galleries' => $galleries,
            'documents' => $documents,
        ]);
    }

    #[Route('/{id}/activate', name: 'app_gallery_activate', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function activate(
        Gallery $gallery,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        PortalNotificationService $portalNotificationService
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $gallery->getFamily());

        if ($gallery->getEtat() === EtatGallery::HIDDEN) {
            $actor = $this->resolveActor();
            $gallery->setEtat(EtatGallery::ACTIF);
            $gallery->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $portalNotificationService->notifyFamily($family, $actor, 'gallery_restored', [
                'galleryId' => $gallery->getId(),
                'galleryName' => $gallery->getName(),
                'route' => 'app_gallery_show',
                'routeParams' => ['id' => $gallery->getId()],
            ]);
        }

        $this->addFlash('success', 'Gallery activated successfully.');

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

    private function galleryNameExistsInFamily(
        GalleryRepository $galleryRepository,
        Family $family,
        ?string $name,
        ?int $excludeId = null
    ): bool {
        if ($name === null || $name === '') {
            return false;
        }

        $existing = $galleryRepository->findOneBy([
            'family' => $family,
            'name' => $name,
        ]);

        if ($existing === null) {
            return false;
        }

        return $excludeId === null || $existing->getId() !== $excludeId;
    }
}
