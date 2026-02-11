<?php

namespace App\Controller;

use App\Entity\Family;
use App\Form\Admin\FamilyAdminType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/ums/families', name: 'portal_admin_families_')]
final class AdminFamilyController extends AbstractController
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
            'createdAt' => 'f.createdAt',
            'name' => 'f.name',
            'joinCode' => 'f.joinCode',
        ];
        $sortField = $allowedSorts[$sort] ?? $allowedSorts['createdAt'];

        $qb = $entityManager->getRepository(Family::class)->createQueryBuilder('f');
        if ($query !== '') {
            $qb->andWhere('LOWER(f.name) LIKE :q OR LOWER(f.joinCode) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $families = $qb
            ->orderBy($sortField, $direction)
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->render('ui_portal/admin/ums/families/index.html.twig', [
            'active_menu' => 'admin-families',
            'families' => $families,
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

        $family = new Family();
        $form = $this->createForm(FamilyAdminType::class, $family);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($family);
            $entityManager->flush();

            $this->addFlash('success', 'Family created.');

            return $this->redirectToRoute('portal_admin_families_index');
        }

        return $this->render('ui_portal/admin/ums/families/form.html.twig', [
            'active_menu' => 'admin-families',
            'title' => 'Create family',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Family $family, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(FamilyAdminType::class, $family);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Family updated.');

            return $this->redirectToRoute('portal_admin_families_index');
        }

        return $this->render('ui_portal/admin/ums/families/form.html.twig', [
            'active_menu' => 'admin-families',
            'title' => 'Edit family',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Family $family, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_family_' . $family->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($family);
        $entityManager->flush();

        $this->addFlash('success', 'Family deleted.');

        return $this->redirectToRoute('portal_admin_families_index');
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
