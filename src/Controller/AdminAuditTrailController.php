<?php

namespace App\Controller;

use App\Entity\AuditTrail;
use App\Form\Admin\AuditTrailAdminType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/ums/audit-trails', name: 'portal_admin_audit_trails_')]
final class AdminAuditTrailController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'createdAt');
        $direction = strtoupper((string) $request->query->get('dir', 'DESC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';
        $allowedSorts = [
            'createdAt' => 'a.createdAt',
            'action' => 'a.action',
        ];
        $sortField = $allowedSorts[$sort] ?? $allowedSorts['createdAt'];

        $qb = $entityManager->getRepository(AuditTrail::class)->createQueryBuilder('a')
            ->addSelect('u', 'f')
            ->leftJoin('a.user', 'u')
            ->leftJoin('a.family', 'f');

        if ($query !== '') {
            $qb->andWhere('LOWER(a.action) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(u.username) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $records = $qb
            ->orderBy($sortField, $direction)
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $this->render('ui_portal/admin/ums/audit_trails/index.html.twig', [
            'active_menu' => 'admin-audit-trails',
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

        $record = new AuditTrail();
        $form = $this->createForm(AuditTrailAdminType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($record);
            $entityManager->flush();

            $this->addFlash('success', 'Audit record created.');

            return $this->redirectToRoute('portal_admin_audit_trails_index');
        }

        return $this->render('ui_portal/admin/ums/audit_trails/form.html.twig', [
            'active_menu' => 'admin-audit-trails',
            'title' => 'Create audit trail',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(AuditTrail $record, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AuditTrailAdminType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Audit record updated.');

            return $this->redirectToRoute('portal_admin_audit_trails_index');
        }

        return $this->render('ui_portal/admin/ums/audit_trails/form.html.twig', [
            'active_menu' => 'admin-audit-trails',
            'title' => 'Edit audit trail',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(AuditTrail $record, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_audit_trail_' . $record->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($record);
        $entityManager->flush();

        $this->addFlash('success', 'Audit record deleted.');

        return $this->redirectToRoute('portal_admin_audit_trails_index');
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
