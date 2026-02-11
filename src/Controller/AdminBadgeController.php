<?php

namespace App\Controller;

use App\Entity\Badge;
use App\Form\Admin\BadgeAdminType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/ums/badges', name: 'portal_admin_badges_')]
final class AdminBadgeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'name');
        $direction = strtoupper((string) $request->query->get('dir', 'ASC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'ASC';
        $allowedSorts = [
            'name' => 'b.name',
            'code' => 'b.code',
            'points' => 'b.requiredPoints',
        ];
        $sortField = $allowedSorts[$sort] ?? $allowedSorts['name'];

        $qb = $entityManager->getRepository(Badge::class)->createQueryBuilder('b');
        if ($query !== '') {
            $qb->andWhere('LOWER(b.name) LIKE :q OR LOWER(b.code) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $badges = $qb
            ->orderBy($sortField, $direction)
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $this->render('ui_portal/admin/ums/badges/index.html.twig', [
            'active_menu' => 'admin-ums-badges',
            'badges' => $badges,
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

        $badge = new Badge();
        $form = $this->createForm(BadgeAdminType::class, $badge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($badge);
            $entityManager->flush();

            $this->addFlash('success', 'Badge created.');

            return $this->redirectToRoute('portal_admin_badges_index');
        }

        return $this->render('ui_portal/admin/ums/badges/form.html.twig', [
            'active_menu' => 'admin-ums-badges',
            'title' => 'Create badge',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Badge $badge, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(BadgeAdminType::class, $badge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Badge updated.');

            return $this->redirectToRoute('portal_admin_badges_index');
        }

        return $this->render('ui_portal/admin/ums/badges/form.html.twig', [
            'active_menu' => 'admin-ums-badges',
            'title' => 'Edit badge',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Badge $badge, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_badge_' . $badge->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($badge);
        $entityManager->flush();

        $this->addFlash('success', 'Badge deleted.');

        return $this->redirectToRoute('portal_admin_badges_index');
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
