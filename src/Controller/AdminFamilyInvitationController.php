<?php

namespace App\Controller;

use App\Entity\FamilyInvitation;
use App\Form\Admin\FamilyInvitationAdminType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/ums/invitations', name: 'portal_admin_invitations_')]
final class AdminFamilyInvitationController extends AbstractController
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
            'createdAt' => 'fi.id',
            'status' => 'fi.status',
            'email' => 'fi.invitedEmail',
        ];
        $sortField = $allowedSorts[$sort] ?? $allowedSorts['createdAt'];

        $qb = $entityManager->getRepository(FamilyInvitation::class)->createQueryBuilder('fi')
            ->addSelect('f', 'u')
            ->leftJoin('fi.family', 'f')
            ->leftJoin('fi.createdBy', 'u');

        if ($query !== '') {
            $qb->andWhere('LOWER(fi.invitedEmail) LIKE :q OR LOWER(fi.joinCode) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $invitations = $qb
            ->orderBy($sortField, $direction)
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->render('ui_portal/admin/ums/invitations/index.html.twig', [
            'active_menu' => 'admin-invitations',
            'invitations' => $invitations,
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

        $invitation = new FamilyInvitation();
        $form = $this->createForm(FamilyInvitationAdminType::class, $invitation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($invitation);
            $entityManager->flush();

            $this->addFlash('success', 'Invitation created.');

            return $this->redirectToRoute('portal_admin_invitations_index');
        }

        return $this->render('ui_portal/admin/ums/invitations/form.html.twig', [
            'active_menu' => 'admin-invitations',
            'title' => 'Create invitation',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(FamilyInvitation $invitation, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(FamilyInvitationAdminType::class, $invitation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Invitation updated.');

            return $this->redirectToRoute('portal_admin_invitations_index');
        }

        return $this->render('ui_portal/admin/ums/invitations/form.html.twig', [
            'active_menu' => 'admin-invitations',
            'title' => 'Edit invitation',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(FamilyInvitation $invitation, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_invitation_' . $invitation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($invitation);
        $entityManager->flush();

        $this->addFlash('success', 'Invitation deleted.');

        return $this->redirectToRoute('portal_admin_invitations_index');
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
