<?php

namespace App\Controller;

use App\Entity\FamilyBadge;
use App\Form\Admin\FamilyBadgeAdminType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/ums/family-badges', name: 'portal_admin_family_badges_')]
final class AdminFamilyBadgeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'awardedAt');
        $direction = strtoupper((string) $request->query->get('dir', 'DESC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';
        $allowedSorts = [
            'awardedAt' => 'fb.awardedAt',
        ];
        $sortField = $allowedSorts[$sort] ?? $allowedSorts['awardedAt'];

        $qb = $entityManager->getRepository(FamilyBadge::class)->createQueryBuilder('fb')
            ->addSelect('f', 'b')
            ->leftJoin('fb.family', 'f')
            ->leftJoin('fb.badge', 'b');

        if ($query !== '') {
            $qb->andWhere('LOWER(f.name) LIKE :q OR LOWER(b.name) LIKE :q OR LOWER(b.code) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $records = $qb
            ->orderBy($sortField, $direction)
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $this->render('ui_portal/admin/ums/family_badges/index.html.twig', [
            'active_menu' => 'admin-family-badges',
            'records' => $records,
            'filters' => [
                'query' => $query,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $record = new FamilyBadge();
        $form = $this->createForm(FamilyBadgeAdminType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($record);
            $entityManager->flush();

            $this->addFlash('success', 'Family badge created.');

            return $this->redirectToRoute('portal_admin_family_badges_index');
        }

        return $this->render('ui_portal/admin/ums/family_badges/form.html.twig', [
            'active_menu' => 'admin-family-badges',
            'title' => 'Create family badge',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(FamilyBadge $record, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(FamilyBadgeAdminType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Family badge updated.');

            return $this->redirectToRoute('portal_admin_family_badges_index');
        }

        return $this->render('ui_portal/admin/ums/family_badges/form.html.twig', [
            'active_menu' => 'admin-family-badges',
            'title' => 'Edit family badge',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(FamilyBadge $record, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_family_badge_' . $record->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($record);
        $entityManager->flush();

        $this->addFlash('success', 'Family badge deleted.');

        return $this->redirectToRoute('portal_admin_family_badges_index');
    }

    /**
     * @return list<string>
     */
    private function collectFormErrors(FormInterface $form): array
    {
        if (!$form->isSubmitted() || $form->isValid()) {
            return [];
        }

        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $messages[] = $error->getMessage();
        }

        return array_values(array_unique($messages));
    }
}
