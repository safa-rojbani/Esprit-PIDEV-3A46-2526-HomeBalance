<?php

namespace App\Controller;

use App\Entity\RoleChangeRequest;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use App\Form\Admin\RoleChangeRequestType;
use App\Form\Admin\UserAdminType;
use App\Repository\FamilyMembershipRepository;
use App\Repository\RoleChangeRequestRepository;
use App\Repository\UserRepository;
use App\Service\CsvExportService;
use App\Service\AccountHealthScoreService;
use App\Service\AuditNotifier;
use App\Service\AuditTrailService;
use App\Service\FamilyManager;
use App\Service\Security\StepUpGuardService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
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
    public function index(Request $request, UserRepository $userRepository, PaginatorInterface $paginator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $filters = [
            'query' => trim((string) $request->query->get('q', '')),
            'systemRole' => $request->query->get('systemRole'),
            'status' => $request->query->get('status'),
            'sort' => $request->query->get('sort', 'createdAt'),
            'direction' => $request->query->get('dir', 'DESC'),
        ];

        $page = max(1, $request->query->getInt('page', 1));
        $usersPagination = $paginator->paginate(
            $userRepository->adminSearchQueryBuilder($filters),
            $page,
            20,
            [
                'distinct' => true,
                'sortFieldParameterName' => '_knp_sort',
                'sortDirectionParameterName' => '_knp_dir',
            ],
        );

        /** @var list<User> $users */
        $users = $usersPagination->getItems();
        $total = (int) $usersPagination->getTotalItemCount();
        $first = $total > 0 ? (($page - 1) * 20) + 1 : 0;
        $last = min($page * 20, $total);

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
            'pagination' => $usersPagination,
            'paginationMeta' => [
                'first' => $first,
                'last' => $last,
                'total' => $total,
            ],
        ]);
    }

    #[Route('/users/{id}', name: 'users_view', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function view(
        User $user,
        Request $request,
        AuditTrailService $auditTrailService,
        FamilyMembershipRepository $membershipRepository,
        AccountHealthScoreService $accountHealthScoreService,
        RoleChangeRequestRepository $roleChangeRequestRepository,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $roleChangeRequest = (new RoleChangeRequest())->setUser($user);
        $roleChangeForm = $this->createForm(RoleChangeRequestType::class, $roleChangeRequest, [
            'action' => $this->generateUrl('portal_admin_users_role_change_request', ['id' => $user->getId()]),
            'method' => 'POST',
        ]);

        return $this->render('ui_portal/admin/users/view.html.twig', [
            'active_menu' => 'admin-users',
            'user' => $this->presentUser($user),
            'familyContext' => $this->buildFamilyContext($user, $membershipRepository),
            'activity' => $auditTrailService->recentForUser($user),
            'accountHealth' => $accountHealthScoreService->evaluate($user),
            'roleChangeForm' => $roleChangeForm->createView(),
            'roleChangeRequests' => $roleChangeRequestRepository->findRecentForUser($user),
            'stepUpPrompt' => $this->buildStepUpPrompt($request, $user),
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
        StepUpGuardService $stepUpGuardService,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($redirect = $this->requireStepUp(
            $request,
            $stepUpGuardService,
            'admin.user.anonymize',
            $user,
            'portal_admin_users_view',
            ['id' => $user->getId()],
        )) {
            return $redirect;
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
        StepUpGuardService $stepUpGuardService,
    ): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('toggle_status_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($redirect = $this->requireStepUp(
            $request,
            $stepUpGuardService,
            'admin.user.toggle_status',
            $user,
            'portal_admin_users_view',
            ['id' => $user->getId()],
        )) {
            return $redirect;
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
        StepUpGuardService $stepUpGuardService,
    ): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('reset_password_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($redirect = $this->requireStepUp(
            $request,
            $stepUpGuardService,
            'admin.user.reset_password',
            $user,
            'portal_admin_users_view',
            ['id' => $user->getId()],
        )) {
            return $redirect;
        }

        $user->setResetToken(bin2hex(random_bytes(32)));
        $user->setResetExpiresAt(new DateTimeImmutable('+1 hour'));
        $entityManager->flush();

        $auditNotifier->recordAndNotify(
            $user,
            'user.reset.requested',
            'reset_requested',
            [],
            $user->getFamily(),
            $this->currentActor(),
        );

        $this->addFlash('success', 'Reset instructions queued.');

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

    #[Route('/users/{id}/role-change-request', name: 'users_role_change_request', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function requestRoleChange(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        AuditTrailService $auditTrailService,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $roleChangeRequest = (new RoleChangeRequest())->setUser($user);
        $form = $this->createForm(RoleChangeRequestType::class, $roleChangeRequest);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Invalid role change request.');

            return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
        }

        $actor = $this->currentActor();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Missing actor.');
        }

        $roleChangeRequest
            ->setRequestedBy($actor)
            ->setStatus(RoleChangeRequest::STATUS_PENDING)
            ->setCreatedAt(new DateTimeImmutable());

        $entityManager->persist($roleChangeRequest);
        $entityManager->flush();

        $requestedRole = $roleChangeRequest->getRequestedRole();
        $auditTrailService->record($user, 'user.role.change.requested', [
            'requestedRole' => $requestedRole?->value,
            'requestId' => $roleChangeRequest->getId(),
            'requestedBy' => $actor->getId(),
        ], $user->getFamily());

        $this->addFlash('success', 'Role change request created.');

        return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/role-change-requests/{requestId}/approve', name: 'users_role_change_approve', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'requestId' => '\d+'])]
    public function approveRoleChange(
        User $user,
        int $requestId,
        Request $request,
        EntityManagerInterface $entityManager,
        RoleChangeRequestRepository $roleChangeRequestRepository,
        AuditTrailService $auditTrailService,
        StepUpGuardService $stepUpGuardService,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $roleChangeRequest = $roleChangeRequestRepository->find($requestId);
        if (!$roleChangeRequest instanceof RoleChangeRequest || $roleChangeRequest->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Role change request not found.');
        }

        if (!$this->isCsrfTokenValid('approve_role_change_' . $roleChangeRequest->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($redirect = $this->requireStepUp(
            $request,
            $stepUpGuardService,
            'admin.user.role_change.approve',
            $user,
            'portal_admin_users_view',
            ['id' => $user->getId()],
        )) {
            return $redirect;
        }

        if ($roleChangeRequest->getStatus() !== RoleChangeRequest::STATUS_PENDING) {
            $this->addFlash('warning', 'This request was already reviewed.');

            return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
        }

        $requestedRole = $roleChangeRequest->getRequestedRole();
        if (!$requestedRole instanceof SystemRole) {
            $this->addFlash('error', 'Requested role is invalid.');

            return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
        }

        $user->setSystemRole($requestedRole);
        $this->syncSecurityRolesForSystemRole($user, $requestedRole);
        $user->setUpdatedAt(new DateTimeImmutable());

        $roleChangeRequest
            ->setStatus(RoleChangeRequest::STATUS_APPROVED)
            ->setReviewedBy($this->currentActor())
            ->setReviewedAt(new DateTimeImmutable());

        $entityManager->flush();

        $auditTrailService->record($user, 'user.role.change.approved', [
            'requestedRole' => $requestedRole->value,
            'requestId' => $roleChangeRequest->getId(),
            'reviewedBy' => $this->currentActor()?->getId(),
        ], $user->getFamily());

        $this->addFlash('success', 'Role change approved.');

        return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/role-change-requests/{requestId}/reject', name: 'users_role_change_reject', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}', 'requestId' => '\d+'])]
    public function rejectRoleChange(
        User $user,
        int $requestId,
        Request $request,
        EntityManagerInterface $entityManager,
        RoleChangeRequestRepository $roleChangeRequestRepository,
        AuditTrailService $auditTrailService,
        StepUpGuardService $stepUpGuardService,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $roleChangeRequest = $roleChangeRequestRepository->find($requestId);
        if (!$roleChangeRequest instanceof RoleChangeRequest || $roleChangeRequest->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Role change request not found.');
        }

        if (!$this->isCsrfTokenValid('reject_role_change_' . $roleChangeRequest->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($redirect = $this->requireStepUp(
            $request,
            $stepUpGuardService,
            'admin.user.role_change.reject',
            $user,
            'portal_admin_users_view',
            ['id' => $user->getId()],
        )) {
            return $redirect;
        }

        if ($roleChangeRequest->getStatus() !== RoleChangeRequest::STATUS_PENDING) {
            $this->addFlash('warning', 'This request was already reviewed.');

            return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
        }

        $roleChangeRequest
            ->setStatus(RoleChangeRequest::STATUS_REJECTED)
            ->setReviewedBy($this->currentActor())
            ->setReviewedAt(new DateTimeImmutable());
        $entityManager->flush();

        $auditTrailService->record($user, 'user.role.change.rejected', [
            'requestedRole' => $roleChangeRequest->getRequestedRole()?->value,
            'requestId' => $roleChangeRequest->getId(),
            'reviewedBy' => $this->currentActor()?->getId(),
        ], $user->getFamily());

        $this->addFlash('success', 'Role change rejected.');

        return $this->redirectToRoute('portal_admin_users_view', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/audit-export', name: 'users_audit_export', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function exportAuditTrail(
        User $user,
        CsvExportService $csvExportService,
        Request $request,
        StepUpGuardService $stepUpGuardService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($redirect = $this->requireStepUp(
            $request,
            $stepUpGuardService,
            'admin.user.audit_export',
            $user,
            'portal_admin_users_view',
            ['id' => $user->getId()],
        )) {
            return $redirect;
        }

        return $csvExportService->streamUserAuditTrail($user);
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
                'joinedAt' => $latestMembership->getJoinedAt(),
                'leftAt' => $latestMembership->getLeftAt(),
            ],
        ];
    }

    private function currentActor(): ?User
    {
        $actor = $this->getUser();

        return $actor instanceof User ? $actor : null;
    }

    private function syncSecurityRolesForSystemRole(User $user, SystemRole $systemRole): void
    {
        $roles = array_values(array_filter(
            $user->getRoles(),
            static fn (string $role): bool => $role !== 'ROLE_ADMIN'
        ));

        if ($systemRole === SystemRole::ADMIN) {
            $roles[] = 'ROLE_ADMIN';
        }

        $user->setRoles(array_values(array_unique($roles)));
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

    private function requireStepUp(
        Request $request,
        StepUpGuardService $stepUpGuardService,
        string $actionKey,
        User $targetUser,
        string $returnRoute,
        array $returnParams = [],
    ): ?RedirectResponse {
        if ($stepUpGuardService->isSatisfied($request, $actionKey, $targetUser)) {
            return null;
        }

        $this->addFlash('warning', 'Face verification is required before this sensitive action.');

        return $this->redirectToRoute('portal_admin_users_view', [
            'id' => $targetUser->getId(),
            'stepup_required' => 1,
            'action_key' => $actionKey,
            'target_user_id' => $targetUser->getId(),
            'return_to' => $this->generateUrl($returnRoute, $returnParams),
        ]);
    }

    /**
     * @return array{required: bool, actionKey: string, targetUserId: string, returnTo: string}
     */
    private function buildStepUpPrompt(Request $request, User $user): array
    {
        $required = $request->query->getBoolean('stepup_required', false);
        $actionKey = trim((string) $request->query->get('action_key', ''));
        $targetUserId = trim((string) $request->query->get('target_user_id', ''));
        $returnTo = trim((string) $request->query->get('return_to', ''));

        if (!$required || $actionKey === '' || $targetUserId !== $user->getId()) {
            return [
                'required' => false,
                'actionKey' => '',
                'targetUserId' => $user->getId(),
                'returnTo' => '',
            ];
        }

        return [
            'required' => true,
            'actionKey' => $actionKey,
            'targetUserId' => $user->getId(),
            'returnTo' => $returnTo !== '' ? $returnTo : $this->generateUrl('portal_admin_users_view', ['id' => $user->getId()]),
        ];
    }
}
