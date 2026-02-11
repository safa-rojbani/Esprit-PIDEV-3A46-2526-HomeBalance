<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use App\Form\Admin\UserAdminType;
use App\Repository\FamilyMembershipRepository;
use App\Repository\UserRepository;
use App\Service\AuditNotifier;
use App\Service\AuditTrailService;
use App\Service\FamilyManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/portal/admin', name: 'portal_admin_')]
final class AdminUserController extends AbstractController
{
    #[Route('/users', name: 'users', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $filters = [
            'query' => trim((string) $request->query->get('q', '')),
            'systemRole' => $request->query->get('systemRole'),
            'status' => $request->query->get('status'),
            'sort' => $request->query->get('sort', 'createdAt'),
            'direction' => $request->query->get('dir', 'DESC'),
        ];

        $users = $userRepository->adminSearch($filters);

        return $this->render('ui_portal/admin/users/index.html.twig', [
            'active_menu' => 'admin-users',
            'filters' => [
                'query' => $filters['query'],
                'systemRole' => $filters['systemRole'],
                'status' => $filters['status'],
                'sort' => $filters['sort'],
                'direction' => $filters['direction'],
                'systemRoleChoices' => $this->systemRoleChoices(),
                'statusChoices' => $this->statusChoices(),
            ],
            'users' => array_map([$this, 'presentUser'], $users),
            'pagination' => [
                'currentRange' => sprintf('%d-%d', $users ? 1 : 0, count($users)),
                'total' => count($users),
                'previous' => null,
                'next' => null,
            ],
        ]);
    }

    #[Route('/users/{id}', name: 'users_view', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function view(
        User $user,
        AuditTrailService $auditTrailService,
        FamilyMembershipRepository $membershipRepository,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('ui_portal/admin/users/view.html.twig', [
            'active_menu' => 'admin-users',
            'user' => $this->presentUser($user),
            'familyContext' => $this->buildFamilyContext($user, $membershipRepository),
            'activity' => $auditTrailService->recentForUser($user),
        ]);
    }

    #[Route('/users/new', name: 'users_new', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $user
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole(SystemRole::CUSTOMER)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setLocale('en')
            ->setTimeZone('UTC');

        $form = $this->createForm(UserAdminType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = (string) $form->get('password')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setUpdatedAt(new DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User created.');

            return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
        }

        return $this->render('ui_portal/admin/users/form.html.twig', [
            'active_menu' => 'admin-users',
            'title' => 'Create user',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/users/{id}/edit', name: 'users_edit', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserAdminType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'User updated.');

            return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
        }

        return $this->render('ui_portal/admin/users/form.html.twig', [
            'active_menu' => 'admin-users',
            'title' => 'Edit user',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/users/{id}/delete', name: 'users_delete', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        \App\Service\UserAnonymizer $userAnonymizer,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $userAnonymizer->anonymize($user);
        $entityManager->flush();

        $this->addFlash('success', 'User anonymized and marked as deleted.');

        return $this->redirectToRoute('portal_admin_users');
    }

    #[Route('/users/{id}/toggle-status', name: 'users_toggle_status', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function toggleStatus(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditNotifier $auditNotifier,
    ): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('toggle_status_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $status = $user->getStatus() === UserStatus::ACTIVE ? UserStatus::SUSPENDED : UserStatus::ACTIVE;
        $user->setStatus($status);
        $user->setUpdatedAt(new DateTimeImmutable());

        $entityManager->flush();

        $auditNotifier->recordAndNotify(
            $user,
            'user.status.changed',
            'status_changed',
            ['status' => $status->name],
            $user->getFamily(),
            $this->currentActor(),
        );

        $this->addFlash('success', 'User status updated.');

        return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/reset-password', name: 'users_reset_password', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function resetPassword(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditNotifier $auditNotifier,
    ): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('reset_password_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user->setResetToken(bin2hex(random_bytes(32)));
        $user->setResetExpiresAt(new \DateTime('+1 hour'));
        $entityManager->flush();

        $auditNotifier->recordAndNotify(
            $user,
            'user.reset.requested',
            'reset_requested',
            [],
            $user->getFamily(),
            $this->currentActor(),
        );

        $this->addFlash('success', 'Reset instructions sent (simulated).');

        return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/detach-family', name: 'users_detach_family', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function detachFamily(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        FamilyMembershipRepository $membershipRepository,
        AuditNotifier $auditNotifier,
    ): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('detach_family_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $family = $user->getFamily();
        if ($family === null) {
            $this->addFlash('warning', 'User is not assigned to a family.');

            return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
        }

        $membership = $membershipRepository->findActiveMembershipForUser($user);
        if ($membership !== null) {
            $membership->leave();
        }

        $user
            ->setFamily(null)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setUpdatedAt(new DateTimeImmutable());

        $entityManager->flush();

        $auditNotifier->recordAndNotify(
            $user,
            'family.detached',
            'family_detached',
            [
                'familyId' => $family->getId(),
                'familyName' => $family->getName(),
            ],
            $family,
            $this->currentActor(),
        );

        $this->addFlash('success', 'User removed from their family.');

        return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/reinvite-family', name: 'users_reinvite_family', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function reinviteFamily(
        User $user,
        Request $request,
        FamilyMembershipRepository $membershipRepository,
        FamilyManager $familyManager,
        AuditNotifier $auditNotifier,
    ): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('reinvite_family_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $family = $user->getFamily();
        if ($family === null) {
            $latestMembership = $membershipRepository->findLatestMembershipForUser($user);
            $family = $latestMembership?->getFamily();
        }

        if ($family === null) {
            $this->addFlash('warning', 'No family history found for this user.');

            return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
        }

        $familyManager->generateJoinCode($family);

        $auditNotifier->recordAndNotify(
            $user,
            'family.invite.resent',
            'family_invite_resent',
            [
                'familyId' => $family->getId(),
                'familyName' => $family->getName(),
                'joinCode' => $family->getJoinCode(),
            ],
            $family,
            $this->currentActor(),
        );

        $this->addFlash('success', 'Family invite resent.');

        return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
    }

    /**
     * @return array<string, string>
     */
    private function systemRoleChoices(): array
    {
        return array_combine(
            array_map(static fn (SystemRole $role) => $role->value, SystemRole::cases()),
            array_map(static fn (SystemRole $role) => ucfirst(strtolower($role->name)), SystemRole::cases()),
        );
    }

    /**
     * @return array<string, string>
     */
    private function statusChoices(): array
    {
        return array_combine(
            array_map(static fn (UserStatus $status) => $status->value, UserStatus::cases()),
            array_map(static fn (UserStatus $status) => ucfirst(strtolower($status->name)), UserStatus::cases()),
        );
    }

    /**
     * @param User $user
     * @return array<string, mixed>
     */
    private function presentUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'systemRoleLabel' => $user->getSystemRole() ? ucfirst(strtolower($user->getSystemRole()->name)) : '—',
            'familyRoleLabel' => $user->getFamilyRole() ? ucfirst(strtolower($user->getFamilyRole()->name)) : null,
            'statusLabel' => $user->getStatus() ? ucfirst(strtolower($user->getStatus()->name)) : 'Unknown',
            'statusBadgeClass' => $user->getStatus() === UserStatus::ACTIVE ? 'bg-label-success' : 'bg-label-warning',
            'lastLogin' => $user->getLastLogin(),
            'createdAt' => $user->getCreatedAt(),
        ];
    }

    /**
     * @return array{current: array<string, mixed>|null, previous: array<string, mixed>|null}
     */
    private function buildFamilyContext(User $user, FamilyMembershipRepository $membershipRepository): array
    {
        $family = $user->getFamily();
        $activeMembership = $family !== null
            ? $membershipRepository->findActiveMembership($family, $user)
            : null;

        $latestMembership = $membershipRepository->findLatestMembershipForUser($user);
        $previousFamily = $family === null && $latestMembership !== null ? $latestMembership->getFamily() : null;

        return [
            'current' => $family === null ? null : [
                'id' => $family->getId(),
                'name' => $family->getName(),
                'joinCode' => $family->getJoinCode(),
                'codeExpiresAt' => $family->getCodeExpiresAt(),
                'joinedAt' => $activeMembership?->getJoinedAt(),
            ],
            'previous' => $previousFamily === null ? null : [
                'id' => $previousFamily->getId(),
                'name' => $previousFamily->getName(),
                'joinedAt' => $latestMembership?->getJoinedAt(),
                'leftAt' => $latestMembership?->getLeftAt(),
            ],
        ];
    }

    private function currentActor(): ?User
    {
        $actor = $this->getUser();

        return $actor instanceof User ? $actor : null;
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
