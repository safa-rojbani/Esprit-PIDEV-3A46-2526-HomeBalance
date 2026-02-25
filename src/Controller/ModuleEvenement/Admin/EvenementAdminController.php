<?php

namespace App\Controller\ModuleEvenement\Admin;

use App\Entity\Evenement;
use App\Entity\EvenementImage;
use App\Form\EvenementAdminType;
use App\Repository\EvenementRepository;
use App\Repository\TypeEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Symfony\Component\Form\FormError;

#[Route('/portal/admin/evenements')]
class EvenementAdminController extends AbstractController
{
    #[Route('', name: 'admin_evenement_index', methods: ['GET'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepository,
        TypeEvenementRepository $typeEvenementRepository,
        PaginatorInterface $paginator
    ): Response
    {
        $admin = $this->requireAdminUser();

        $typeId = $request->query->get('type');
        $search = trim((string) $request->query->get('q', ''));
        $selectedType = null;

        if ($typeId !== null && $typeId !== '') {
            $selectedType = $typeEvenementRepository->findOneBy([
                'id' => (int) $typeId,
                'family' => null,
            ]);
        }

        $qb = $evenementRepository->findAdminDefaultsQueryBuilder($admin, $selectedType, $search);
        $evenements = $paginator->paginate(
            $qb,
            max(1, (int) $request->query->get('page', 1)),
            10
        );

        $year = (int) (new \DateTimeImmutable())->format('Y');
        $monthLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        $monthCounts = array_fill(0, 12, 0);
        foreach ($evenementRepository->countByMonthForAdmin($admin, $year) as $row) {
            $idx = (int) $row['month'] - 1;
            if ($idx >= 0 && $idx < 12) {
                $monthCounts[$idx] = (int) $row['total'];
            }
        }

        $typeLabels = [];
        $typeCounts = [];
        foreach ($evenementRepository->countByTypeForAdmin($admin) as $row) {
            $typeLabels[] = (string) $row['label'];
            $typeCounts[] = (int) $row['total'];
        }

        return $this->render('admin/evenement/index.html.twig', [
            'evenements' => $evenements,
            'types' => $typeEvenementRepository->findBy(['family' => null], ['nom' => 'ASC']),
            'selectedType' => $selectedType,
            'search' => $search,
            'monthLabels' => $monthLabels,
            'monthCounts' => $monthCounts,
            'typeLabels' => $typeLabels,
            'typeCounts' => $typeCounts,
            'statsYear' => $year,
        ]);
    }

    #[Route('/new', name: 'admin_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, EvenementRepository $evenementRepository): Response
    {
        $admin = $this->requireAdminUser();

        $evenement = new Evenement();

        $form = $this->createForm(EvenementAdminType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();
            $evenement->setDateCreation($now);
            $evenement->setDateModification($now);
            $evenement->setFamily(null);
            $evenement->setCreatedBy($admin);
            $evenement->setShareWithFamily(true);

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

            $start = $evenement->getDateDebut();
            $end = $evenement->getDateFin() ?? $start;
            if ($end < $start) {
                $form->get('dateFin')->addError(new FormError('La date de fin doit être après la date de début.'));
                return $this->render('admin/evenement/new.html.twig', [
                    'evenement' => $evenement,
                    'form' => $form->createView(),
                ]);
            }
            $conflicts = $evenementRepository->findConflictsForAdmin($admin, $start, $end);
            if (count($conflicts) > 0) {
                $this->addFlash('warning', $this->formatConflictMessage($conflicts));
                return $this->redirectToRoute('admin_calendar');
            }
            $entityManager->persist($evenement);
            $entityManager->flush();

            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('admin/evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Evenement $evenement): Response
    {
        $this->assertDefaultOwnedByAdmin($this->requireAdminUser(), $evenement);

        return $this->render('admin/evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_evenement_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, EvenementRepository $evenementRepository): Response
    {
        $this->assertDefaultOwnedByAdmin($this->requireAdminUser(), $evenement);

        $form = $this->createForm(EvenementAdminType::class, $evenement);
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
                return $this->render('admin/evenement/edit.html.twig', [
                    'evenement' => $evenement,
                    'form' => $form->createView(),
                ]);
            }
            $conflicts = $evenementRepository->findConflictsForAdmin(
                $this->requireAdminUser(),
                $start,
                $end,
                $evenement->getId()
            );
            if (count($conflicts) > 0) {
                $this->addFlash('warning', $this->formatConflictMessage($conflicts));
                return $this->redirectToRoute('admin_calendar');
            }
            $entityManager->flush();

            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('admin/evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $this->assertDefaultOwnedByAdmin($this->requireAdminUser(), $evenement);

        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_evenement_index');
    }

    private function requireAdminUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function assertDefaultOwnedByAdmin(User $admin, Evenement $evenement): void
    {
        $owner = $evenement->getCreatedBy();
        if ($evenement->getFamily() !== null || !$owner instanceof User || $owner->getId() !== $admin->getId()) {
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
