<?php

namespace App\Controller;

use App\DTO\FamilyMembershipInput;
use App\Entity\FamilyMembership;
use App\Form\Admin\FamilyMembershipAdminType;
use App\Form\Admin\FamilyMembershipCreateType;
use App\Enum\FamilyRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/ums/memberships', name: 'portal_admin_memberships_')]
final class AdminFamilyMembershipController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'joinedAt');
        $direction = strtoupper((string) $request->query->get('dir', 'DESC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';
        $allowedSorts = [
            'joinedAt' => 'm.joinedAt',
            'role' => 'm.role',
        ];
        $sortField = $allowedSorts[$sort] ?? $allowedSorts['joinedAt'];

        $qb = $entityManager->getRepository(FamilyMembership::class)->createQueryBuilder('m')
            ->addSelect('u', 'f')
            ->leftJoin('m.user', 'u')
            ->leftJoin('m.family', 'f');

        if ($query !== '') {
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.username) LIKE :q OR LOWER(f.name) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $memberships = $qb
            ->orderBy($sortField, $direction)
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($memberships as $membership) {
            $family = $membership->getFamily();
            $familyId = $family?->getId() ?? 0;
            $grouped[$familyId] ??= [
                'family' => $family,
                'memberships' => [],
            ];
            $grouped[$familyId]['memberships'][] = $membership;
        }

        return $this->render('ui_portal/admin/ums/memberships/index.html.twig', [
            'active_menu' => 'admin-memberships',
            'memberships' => $memberships,
            'groupedMemberships' => $grouped,
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

        $input = new FamilyMembershipInput();
        $form = $this->createForm(FamilyMembershipCreateType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$input->family || !$input->user || !$input->role) {
                $this->addFlash('error', 'Family, user, and role are required.');

                return $this->redirectToRoute('portal_admin_memberships_new');
            }

            $membership = new FamilyMembership($input->family, $input->user, $input->role);
            if ($input->joinedAt) {
                $membership->setJoinedAt($input->joinedAt);
            }
            if ($input->leftAt) {
                $membership->setLeftAt($input->leftAt);
            }

            $this->syncUserFamily($membership);

            $entityManager->persist($membership);
            $entityManager->flush();

            $this->addFlash('success', 'Membership created.');

            return $this->redirectToRoute('portal_admin_memberships_index');
        }

        return $this->render('ui_portal/admin/ums/memberships/form.html.twig', [
            'active_menu' => 'admin-memberships',
            'title' => 'Create membership',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(FamilyMembership $membership, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(FamilyMembershipAdminType::class, $membership);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncUserFamily($membership);
            $entityManager->flush();
            $this->addFlash('success', 'Membership updated.');

            return $this->redirectToRoute('portal_admin_memberships_index');
        }

        return $this->render('ui_portal/admin/ums/memberships/form.html.twig', [
            'active_menu' => 'admin-memberships',
            'title' => 'Edit membership',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(FamilyMembership $membership, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_membership_' . $membership->getFamily()->getId() . '_' . $membership->getUser()->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($membership);
        $entityManager->flush();

        $this->addFlash('success', 'Membership deleted.');

        return $this->redirectToRoute('portal_admin_memberships_index');
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

    private function syncUserFamily(FamilyMembership $membership): void
    {
        $user = $membership->getUser();
        $family = $membership->getFamily();

        if ($membership->getLeftAt() === null) {
            $user->setFamily($family);
            $user->setFamilyRole($membership->getRole());
            return;
        }

        if ($user->getFamily() && $user->getFamily() === $family) {
            $user->setFamily(null);
            $user->setFamilyRole(FamilyRole::SOLO);
        }
    }
}
