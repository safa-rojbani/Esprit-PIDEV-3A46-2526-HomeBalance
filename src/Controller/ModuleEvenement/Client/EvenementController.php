<?php

namespace App\Controller\ModuleEvenement\Client;

use App\Entity\Evenement;
use App\Entity\EvenementImage;
use App\Entity\Rappel;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\RappelRepository;
use App\Repository\TypeEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;
use App\Entity\Family;
use Symfony\Component\Form\FormError;

#[Route('/portal/evenements')]
class EvenementController extends AbstractController
{
    #[Route('', name: 'app_evenement_index', methods: ['GET'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepository,
        TypeEvenementRepository $typeEvenementRepository,
        ActiveFamilyResolver $familyResolver,
        PaginatorInterface $paginator
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);

        $typeId = $request->query->get('type');
        $search = trim((string) $request->query->get('q', ''));
        $selectedType = null;

        if ($typeId !== null && $typeId !== '') {
            $selectedType = $typeEvenementRepository->find($typeId);
        }

        $qb = $evenementRepository->findForFamilyWithFiltersQueryBuilder($family, $selectedType, $search);
        $evenements = $paginator->paginate(
            $qb,
            max(1, (int) $request->query->get('page', 1)),
            10
        );

        $year = (int) (new \DateTimeImmutable())->format('Y');
        $monthLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        $monthCounts = array_fill(0, 12, 0);
        foreach ($evenementRepository->countByMonthForFamily($family, $year) as $row) {
            $idx = (int) $row['month'] - 1;
            if ($idx >= 0 && $idx < 12) {
                $monthCounts[$idx] = (int) $row['total'];
            }
        }

        $typeLabels = [];
        $typeCounts = [];
        foreach ($evenementRepository->countByTypeForFamily($family) as $row) {
            $typeLabels[] = (string) $row['label'];
            $typeCounts[] = (int) $row['total'];
        }

        $typesQb = $typeEvenementRepository->createQueryBuilder('t')
            ->orderBy('t.nom', 'ASC');
        $typesQb->andWhere('(t.family = :family OR t.family IS NULL)')
            ->setParameter('family', $family);
        $types = $typesQb->getQuery()->getResult();

        return $this->render('app/evenement/index.html.twig', [
            'evenements' => $evenements,
            'types' => $types,
            'selectedType' => $selectedType,
            'search' => $search,
            'monthLabels' => $monthLabels,
            'monthCounts' => $monthCounts,
            'typeLabels' => $typeLabels,
            'typeCounts' => $typeCounts,
            'statsYear' => $year,
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver, EvenementRepository $evenementRepository): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $user = $this->getUser();

        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement, [
            'family' => $family,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }
            $evenement->setCreatedBy($user);
            $evenement->setFamily($family);

            $now = new \DateTimeImmutable();
            $evenement->setDateCreation($now);
            $evenement->setDateModification($now);

            $uploadedFiles = $form->get('imageFiles')->getData();
            if (is_array($uploadedFiles)) {
                foreach ($uploadedFiles as $uploadedFile) {
                    if ($uploadedFile === null) {
                        continue;
                    }
                    $image = new EvenementImage();
                    $image->setImageFile($uploadedFile);
                    $evenement->addImage($image);
                }
            }

            // Default reminder: 1 day before (1440 minutes) only if event is >= 24h away
            $now = new \DateTimeImmutable();
            $evenement->setDateCreation($now);
            $evenement->setDateModification($now);

            $start = $evenement->getDateDebut();
            $end = $evenement->getDateFin() ?? $start;
            if ($end < $start) {
                $form->get('dateFin')->addError(new FormError('La date de fin doit être après la date de début.'));
                return $this->render('app/evenement/new.html.twig', [
                    'evenement' => $evenement,
                    'form' => $form->createView(),
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }
            $conflicts = $evenementRepository->findConflictsForFamily($family, $start, $end);
            if (count($conflicts) > 0) {
                $message = $this->formatConflictMessage($conflicts);
                $this->addFlash('warning', $message);
                $form->addError(new FormError($message));
                return $this->render('app/evenement/new.html.twig', [
                    'evenement' => $evenement,
                    'form' => $form->createView(),
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $entityManager->persist($evenement);
            $hoursUntil = ($evenement->getDateDebut()->getTimestamp() - $now->getTimestamp()) / 3600;
            $offsetMinutes = $hoursUntil >= 24 ? 1440 : 30;
            $defaultRappel = new Rappel();
            $defaultRappel->setEvenement($evenement);
            $defaultRappel->setUser($user);
            $defaultRappel->setFamily($family);
            $defaultRappel->setOffsetMinutes($offsetMinutes);
            $defaultRappel->setCanal('popup');
            $defaultRappel->setActif(true);
            $defaultRappel->setEstLu(false);
            $scheduledAt = $evenement->getDateDebut()->sub(new \DateInterval('PT' . $offsetMinutes . 'M'));
            if ($scheduledAt < $now) {
                $scheduledAt = $now;
            }
            $defaultRappel->setScheduledAt($scheduledAt);
            $entityManager->persist($defaultRappel);
            $entityManager->flush();

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('app/evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ], new Response('', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Evenement $evenement, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertCanView($family, $evenement);

        return $this->render('app/evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver, EvenementRepository $evenementRepository): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $evenement->getFamily());

        if ($evenement->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(EvenementType::class, $evenement, [
            'family' => $family,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFiles = $form->get('imageFiles')->getData();
            if (is_array($uploadedFiles)) {
                foreach ($uploadedFiles as $uploadedFile) {
                    if ($uploadedFile === null) {
                        continue;
                    }
                    $image = new EvenementImage();
                    $image->setImageFile($uploadedFile);
                    $evenement->addImage($image);
                }
            }

            $evenement->setDateModification(new \DateTimeImmutable());

            $start = $evenement->getDateDebut();
            $end = $evenement->getDateFin() ?? $start;
            if ($end < $start) {
                $form->get('dateFin')->addError(new FormError('La date de fin doit être après la date de début.'));
                return $this->render('app/evenement/edit.html.twig', [
                    'evenement' => $evenement,
                    'form' => $form->createView(),
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }
            $conflicts = $evenementRepository->findConflictsForFamily($family, $start, $end, $evenement->getId());
            if (count($conflicts) > 0) {
                $message = $this->formatConflictMessage($conflicts);
                $this->addFlash('warning', $message);
                $form->addError(new FormError($message));
                return $this->render('app/evenement/edit.html.twig', [
                    'evenement' => $evenement,
                    'form' => $form->createView(),
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('app/evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ], new Response('', $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver, RappelRepository $rappelRepository): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $evenement->getFamily());

        if ($evenement->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->request->get('_token'))) {
            $rappels = $rappelRepository->findBy(['evenement' => $evenement]);
            foreach ($rappels as $rappel) {
                $entityManager->remove($rappel);
            }
            $entityManager->remove($evenement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/images/{imageId}/delete', name: 'app_evenement_image_delete', methods: ['POST'], requirements: ['id' => '\\d+', 'imageId' => '\\d+'])]
    public function deleteImage(
        Request $request,
        Evenement $evenement,
        int $imageId,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver
    ): Response {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $evenement->getFamily());

        if ($evenement->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $image = $entityManager->getRepository(EvenementImage::class)->find($imageId);
        if ($image === null || $image->getEvenement()?->getId() !== $evenement->getId()) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_image'.$image->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($image);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_evenement_edit', ['id' => $evenement->getId()]);
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
        if ($targetFamily === null || $targetFamily->getId() != $family->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertCanView(Family $family, Evenement $evenement): void
    {
        $eventFamily = $evenement->getFamily();
        if ($eventFamily === null) {
            return;
        }

        if ($eventFamily->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * @param Evenement[] $conflicts
     */
    private function formatConflictMessage(array $conflicts): string
    {
        $first = $conflicts[0];
        $date = $first->getDateDebut() ? $first->getDateDebut()->format('d/m/Y H:i') : '';
        $extra = count($conflicts) > 1 ? (' (+'.(count($conflicts) - 1).' autre(s))') : '';

        return sprintf('⚠ Conflit avec %s du %s%s', $first->getTitre(), $date, $extra);
    }
}
