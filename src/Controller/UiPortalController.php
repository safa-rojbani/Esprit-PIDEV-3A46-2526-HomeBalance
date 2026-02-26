<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Form\AccountProfileFormType;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\AuditTrailService;
use App\Service\AvatarStorage;
use App\Service\EmailVerificationService;
use App\Service\NotificationService;
use App\Service\PasswordResetService;
use App\Service\PreferencesService;
use App\Service\DashboardViewModelFactory;
use App\Service\UserManager;
use App\Service\UserMetricsFormatter;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Entity\Family;
use App\Entity\FamilyInvitation;
use App\Entity\FamilyMembership;
use App\Entity\Badge;
use App\Entity\FamilyBadge;
use App\Entity\AccountNotification;
use App\Repository\AccountNotificationRepository;
use App\Entity\AuditTrail;
use Doctrine\ORM\EntityRepository;

#[Route('/portal', name: 'portal_')]
final class UiPortalController extends AbstractController
{
    private const DICEBEAR_HOST = 'api.dicebear.com';
    private const DEFAULT_NOTIFICATION_MATRIX = [
        'new_for_you' => ['email' => true, 'browser' => true, 'app' => true],
        'account_activity' => ['email' => true, 'browser' => true, 'app' => true],
        'new_browser' => ['email' => true, 'browser' => true, 'app' => false],
        'new_device' => ['email' => true, 'browser' => false, 'app' => false],
    ];

    #[Route('/account', name: 'account', methods: ['GET', 'POST'])]
    public function account(
        Request $request,
        UserManager $userManager,
        AuditTrailService $auditTrailService,
        NotificationService $notificationService,
        UserMetricsFormatter $metricsFormatter,
        AvatarStorage $avatarStorage,
        EntityManagerInterface $entityManager
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $section = (string) $request->request->get('section', 'profile');
        $form = $this->createForm(AccountProfileFormType::class, $this->buildAccountFormData($user));
        if (!($request->isMethod('POST') && $section === 'password')) {
            $form->handleRequest($request);
        }

        if ($section === 'password' && $request->isMethod('POST')) {
            return $this->handlePasswordChange($request, $user, $userManager, $auditTrailService, $notificationService);
        }

        $avatarChanged = false;
        $avatarErrors = [];

        if ($section === 'profile' && $request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_update', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            [$avatarChanged, $avatarErrors] = $this->handleAvatarMutation($request, $user, $avatarStorage);
            if ($avatarChanged) {
                $entityManager->flush();
            }

            if ($avatarErrors !== []) {
                foreach ($avatarErrors as $error) {
                    $this->addFlash('error', $error);
                }
            } elseif ($avatarChanged) {
                $this->addFlash('success', 'Avatar updated.');
            }
        }

        if ($section === 'profile' && $form->isSubmitted()) {
            if ($form->isValid()) {
                $changes = $userManager->updateAccountProfile($user, $form->getData());

                if ($avatarChanged) {
                    $changes[] = 'avatar';
                }

                $distinctChanges = array_values(array_unique($changes));
                if ($distinctChanges !== []) {
                    $auditTrailService->record($user, 'user.profile.updated', ['fields' => $distinctChanges]);
                    $notificationService->sendAccountNotification($user, 'profile_updated', ['fields' => $distinctChanges]);
                }

                if ($avatarErrors === []) {
                    $this->addFlash('success', 'Account details updated.');

                    return $this->redirectToRoute('portal_account');
                }
            }

            $this->addFlash('error', 'Please correct the highlighted errors.');
        }

        $accountView = $form->getData();
        if (!\is_array($accountView)) {
            $accountView = $this->buildAccountFormData($user);
        }
        $accountView['avatarPath'] = $user->getAvatarPath();

        return $this->render('ui_portal/account-settings-account.html.twig', [
            'active_menu' => 'account',
            'accountForm' => $accountView,
            'accountErrors' => $this->collectFormErrors($form),
            'metrics' => $metricsFormatter->summarize($user),
        ]);
    }

    #[Route('/account/delete', name: 'account_delete', methods: ['POST'])]
    public function deleteAccount(
        Request $request,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        AuditTrailService $auditTrailService,
        NotificationService $notificationService,
        \App\Service\UserAnonymizer $userAnonymizer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('account_delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $userAnonymizer->anonymize($user);

        $entityManager->flush();
        $tokenStorage->setToken(null);

        $auditTrailService->record($user, 'user.deleted');
        $notificationService->sendAccountNotification($user, 'account_deleted');

        $this->addFlash('success', 'Your account has been scheduled for deletion.');

        return $this->redirectToRoute('portal_auth_login');
    }

    #[Route('/account/notifications', name: 'account_notifications', methods: ['GET', 'POST'])]
    public function notifications(
        Request $request,
        EntityManagerInterface $entityManager,
        AuditTrailService $auditTrailService,
        NotificationService $notificationService,
        AccountNotificationRepository $accountNotificationRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $preferences = $user->getPreferences() ?? [];
        $notifications = $preferences['notifications']['matrix'] ?? self::DEFAULT_NOTIFICATION_MATRIX;
        $delivery = $preferences['notifications']['delivery'] ?? 'online';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('notifications_update', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            /** @var array<string, array<string, mixed>> $submitted */
            $submitted = $request->request->all('notifications') ?? [];
            $notifications = $this->normalizeNotificationMatrix($submitted);
            $delivery = $request->request->get('sendNotification', 'online');

            $preferences['notifications'] = [
                'matrix' => $notifications,
                'delivery' => $delivery,
            ];
            $user->setPreferences($preferences);
            $user->setUpdatedAt(new DateTimeImmutable());

            $entityManager->flush();

            $auditTrailService->record($user, 'user.notifications.updated', [
                'delivery' => $delivery,
            ]);
            $notificationService->sendAccountNotification($user, 'notifications_updated', [
                'delivery' => $delivery,
            ]);

            $this->addFlash('success', 'Notification preferences updated.');

            return $this->redirectToRoute('portal_account_notifications');
        }

        return $this->render('ui_portal/account-settings-notifications.html.twig', [
            'active_menu' => 'notifications',
            'notificationMatrix' => $notifications,
            'notificationDelivery' => $delivery,
            'notificationRows' => $this->notificationRows(),
            'recentInAppNotifications' => $accountNotificationRepository->findRecentForUser($user, 20, 'app'),
        ]);
    }

    #[Route('/account/notifications/quick', name: 'account_notifications_quick', methods: ['POST'])]
    public function quickNotificationToggle(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $token = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('notifications_quick', $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $type = isset($payload['type']) ? (string) $payload['type'] : '';
        $channel = isset($payload['channel']) ? (string) $payload['channel'] : '';
        $enabled = isset($payload['enabled']) ? (bool) $payload['enabled'] : false;

        if ($type === '' || $channel === '') {
            return new JsonResponse(['error' => 'Missing type or channel.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        $preferences = $user->getPreferences() ?? [];
        $matrix = $preferences['notifications']['matrix'] ?? self::DEFAULT_NOTIFICATION_MATRIX;

        if (!array_key_exists($type, $matrix) || !array_key_exists($channel, $matrix[$type])) {
            return new JsonResponse(['error' => 'Unknown notification option.'], Response::HTTP_BAD_REQUEST);
        }

        $matrix[$type][$channel] = $enabled;
        $preferences['notifications']['matrix'] = $matrix;
        $user->setPreferences($preferences);
        $user->setUpdatedAt(new DateTimeImmutable());

        $entityManager->flush();

        return new JsonResponse(['success' => true, 'matrix' => $matrix]);
    }

    #[Route('/account/notifications/browser-feed', name: 'account_notifications_browser_feed', methods: ['GET'])]
    public function browserNotificationFeed(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $sinceId = max(0, (int) $request->query->get('since', 0));

        $records = $entityManager->getRepository(AccountNotification::class)
            ->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.id > :sinceId')
            ->andWhere('n.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('sinceId', $sinceId)
            ->setParameter('statuses', ['PENDING', 'SENT'])
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(25)
            ->getQuery()
            ->getResult();

        $preferences = $user->getPreferences() ?? [];
        $matrix = $preferences['notifications']['matrix'] ?? self::DEFAULT_NOTIFICATION_MATRIX;

        $maxId = $sinceId;
        $notifications = [];

        foreach ($records as $record) {
            if (!$record instanceof AccountNotification) {
                continue;
            }

            $type = $this->notificationTypeForKey((string) $record->getKey());
            $channels = $matrix[$type] ?? [];
            $browserEnabled = (bool) ($channels['browser'] ?? false);
            $appEnabled = (bool) ($channels['app'] ?? false);
            if (!$browserEnabled && !$appEnabled) {
                continue;
            }

            $summary = $this->notificationSummary((string) $record->getKey(), $record->getPayload() ?? []);
            $notificationId = (int) $record->getId();
            $maxId = max($maxId, $notificationId);

            $notifications[] = [
                'id' => $notificationId,
                'type' => $type,
                'title' => $summary['title'],
                'body' => $summary['body'],
                'channels' => [
                    'browser' => $browserEnabled,
                    'app' => $appEnabled,
                ],
                'createdAt' => $record->getCreatedAt()?->format(DATE_ATOM),
            ];
        }

        return new JsonResponse([
            'notifications' => $notifications,
            'maxId' => $maxId,
        ]);
    }
    #[Route('/account/preferences', name: 'account_preferences', methods: ['GET', 'POST'])]
    public function accountPreferences(Request $request, PreferencesService $preferencesService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $context = [
            'data' => $preferencesService->viewDataFor($user),
            'errors' => [],
            'channels' => PreferencesService::CHANNELS,
            'topics' => PreferencesService::TOPICS,
            'modules' => PreferencesService::MODULES,
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_preferences', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            if ('preferences' === $request->request->get('section')) {
                $result = $preferencesService->savePreferences($user, $request->request->all('preferences') ?? []);
                $context['data'] = $result->data;
                $context['errors'] = $result->errors;

                if ($result->success) {
                    $this->addFlash('success', 'Signal preferences saved.');

                    return $this->redirectToRoute('portal_account_preferences');
                }

                $this->addFlash('error', 'Please review the highlighted fields.');
            }
        }

        return $this->render('ui_portal/account-settings-preferences.html.twig', [
            'active_menu' => 'preferences',
            'preferences' => $context,
        ]);
    }

    #[Route('/account/connections', name: 'account_connections', methods: ['GET'])]
    public function connections(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->redirectToRoute('portal_account_preferences');
    }

    #[Route('/auth/login', name: 'auth_login', methods: ['GET', 'POST'])]
    public function authLogin(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->remove('_security.main.target_path');
            $session->remove('_security_main.target_path');
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('portal_dashboard');
        }

        return $this->render('ui_portal/auth-login-basic.html.twig', [
            'active_menu' => 'auth-login',
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        DashboardViewModelFactory $viewModelFactory,
        \App\Repository\EvenementRepository $evenementRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $preferences = $user->getPreferences() ?? [];
        $notificationMatrix = $preferences['notifications']['matrix'] ?? self::DEFAULT_NOTIFICATION_MATRIX;
        $notificationDelivery = $preferences['notifications']['delivery'] ?? 'online';
        $notificationChannels = [
            'email' => ['label' => 'Email', 'icon' => 'bx-envelope'],
            'browser' => ['label' => 'Browser', 'icon' => 'bx-bell'],
            'app' => ['label' => 'Mobile App', 'icon' => 'bx-mobile-alt'],
        ];

        $viewModel = $viewModelFactory->build($user);

        $year = (int) (new \DateTimeImmutable())->format('Y');
        $monthLabels = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthCounts = array_fill(0, 12, 0);
        $typeLabels = [];
        $typeCounts = [];

        $family = $user->getFamily();
        if ($family !== null) {
            foreach ($evenementRepository->countByMonthForFamily($family, $year) as $row) {
                $idx = (int) $row['month'] - 1;
                if ($idx >= 0 && $idx < 12) {
                    $monthCounts[$idx] = (int) $row['total'];
                }
            }
            foreach ($evenementRepository->countByTypeForFamily($family) as $row) {
                $typeLabels[] = (string) $row['label'];
                $typeCounts[] = (int) $row['total'];
            }
        } else {
            $visibleEvents = $evenementRepository->findVisibleForUser($user);
            $typeTotals = [];
            foreach ($visibleEvents as $event) {
                $date = $event->getDateDebut();
                if ($date) {
                    $idx = (int) $date->format('n') - 1;
                    if ($idx >= 0 && $idx < 12) {
                        $monthCounts[$idx]++;
                    }
                }
                $type = $event->getTypeEvenement();
                if ($type) {
                    $label = (string) $type->getNom();
                    $typeTotals[$label] = ($typeTotals[$label] ?? 0) + 1;
                }
            }
            foreach ($typeTotals as $label => $total) {
                $typeLabels[] = $label;
                $typeCounts[] = $total;
            }
        }

        return $this->render('ui_portal/dashboard.html.twig', [
            'active_menu' => 'dashboard',
            'user' => $user,
            'recentActivity' => $viewModel['recentActivity'],
            'metrics' => $viewModel['metrics'],
            'notificationMatrix' => $notificationMatrix,
            'notificationRows' => $this->notificationRows(),
            'notificationChannels' => $notificationChannels,
            'engagement' => $viewModel['engagement'],
            'statsYear' => $year,
            'monthLabels' => $monthLabels,
            'monthCounts' => $monthCounts,
            'typeLabels' => $typeLabels,
            'typeCounts' => $typeCounts,
        ]);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $query = trim((string) $request->query->get('q', ''));
        $results = [
            'users' => [],
            'families' => [],
            'invitations' => [],
            'memberships' => [],
            'badges' => [],
            'familyBadges' => [],
            'accountNotifications' => [],
            'auditTrails' => [],
        ];

        if ($query !== '') {
            $needle = '%' . mb_strtolower($query) . '%';

            /** @var EntityRepository $userRepo */
            $userRepo = $entityManager->getRepository(User::class);
            $results['users'] = $userRepo->createQueryBuilder('u')
                ->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.username) LIKE :q OR LOWER(u.FirstName) LIKE :q OR LOWER(u.LastName) LIKE :q')
                ->setParameter('q', $needle)
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            /** @var EntityRepository $familyRepo */
            $familyRepo = $entityManager->getRepository(Family::class);
            $results['families'] = $familyRepo->createQueryBuilder('f')
                ->andWhere('LOWER(f.name) LIKE :q OR LOWER(f.joinCode) LIKE :q')
                ->setParameter('q', $needle)
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            /** @var EntityRepository $invitationRepo */
            $invitationRepo = $entityManager->getRepository(FamilyInvitation::class);
            $results['invitations'] = $invitationRepo->createQueryBuilder('i')
                ->leftJoin('i.family', 'f')
                ->addSelect('f')
                ->andWhere('LOWER(i.invitedEmail) LIKE :q OR LOWER(i.joinCode) LIKE :q OR LOWER(f.name) LIKE :q')
                ->setParameter('q', $needle)
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            /** @var EntityRepository $membershipRepo */
            $membershipRepo = $entityManager->getRepository(FamilyMembership::class);
            $results['memberships'] = $membershipRepo->createQueryBuilder('m')
                ->leftJoin('m.user', 'u')
                ->leftJoin('m.family', 'f')
                ->addSelect('u', 'f')
                ->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.username) LIKE :q OR LOWER(f.name) LIKE :q')
                ->setParameter('q', $needle)
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            /** @var EntityRepository $badgeRepo */
            $badgeRepo = $entityManager->getRepository(Badge::class);
            $results['badges'] = $badgeRepo->createQueryBuilder('b')
                ->andWhere('LOWER(b.name) LIKE :q OR LOWER(b.code) LIKE :q')
                ->setParameter('q', $needle)
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            /** @var EntityRepository $familyBadgeRepo */
            $familyBadgeRepo = $entityManager->getRepository(FamilyBadge::class);
            $results['familyBadges'] = $familyBadgeRepo->createQueryBuilder('fb')
                ->leftJoin('fb.family', 'f')
                ->leftJoin('fb.badge', 'b')
                ->addSelect('f', 'b')
                ->andWhere('LOWER(f.name) LIKE :q OR LOWER(b.name) LIKE :q OR LOWER(b.code) LIKE :q')
                ->setParameter('q', $needle)
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            /** @var EntityRepository $notificationRepo */
            $notificationRepo = $entityManager->getRepository(AccountNotification::class);
            $results['accountNotifications'] = $notificationRepo->createQueryBuilder('n')
                ->leftJoin('n.user', 'u')
                ->addSelect('u')
                ->andWhere('LOWER(n.key) LIKE :q OR LOWER(n.channel) LIKE :q OR LOWER(n.status) LIKE :q OR LOWER(u.email) LIKE :q')
                ->setParameter('q', $needle)
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            /** @var EntityRepository $auditRepo */
            $auditRepo = $entityManager->getRepository(AuditTrail::class);
            $results['auditTrails'] = $auditRepo->createQueryBuilder('a')
                ->leftJoin('a.user', 'u')
                ->leftJoin('a.family', 'f')
                ->addSelect('u', 'f')
                ->andWhere('LOWER(a.action) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(f.name) LIKE :q')
                ->setParameter('q', $needle)
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
        }

        return $this->render('ui_portal/search-results.html.twig', [
            'active_menu' => 'search',
            'query' => $query,
            'results' => $results,
        ]);
    }

    #[Route('/auth/logout', name: 'auth_logout', methods: ['GET'])]
    public function authLogout(): void
    {
        throw new \LogicException('Logout is handled by Symfony security layer.');
    }

    #[Route('/auth/register', name: 'auth_register', methods: ['GET', 'POST'])]
    public function authRegister(
        Request $request,
        UserManager $userManager,
        UserRepository $userRepository,
        AuditTrailService $auditTrailService,
        NotificationService $notificationService,
        EmailVerificationService $emailVerificationService
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('portal_account');
        }

        $form = $this->createForm(RegistrationFormType::class, [
            'username' => '',
            'first_name' => '',
            'last_name' => '',
            'email' => '',
        ]);
        $form->handleRequest($request);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register_user', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $username = (string) $form->get('username')->getData();
            $email = (string) $form->get('email')->getData();

            if ($username !== '' && $userRepository->findOneBy(['username' => $username])) {
                $form->get('username')->addError(new FormError('That username is already taken.'));
            }
            if ($email !== '' && $userRepository->findOneBy(['email' => $email])) {
                $form->get('email')->addError(new FormError('An account already exists for this email.'));
            }
        }

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();
                $data['password'] = (string) $form->get('password')->getData();

                $user = $userManager->registerUser($data);

                $auditTrailService->record($user, 'user.registered');
                $notificationService->sendAccountNotification($user, 'welcome');
                $emailVerificationService->sendVerification($user);

                $this->addFlash('success', 'Account created! Check your inbox to verify your email before signing in.');

                return $this->redirectToRoute('portal_auth_login');
            }

            $this->addFlash('error', 'Please review the highlighted fields.');
        }

        $registrationData = $form->getData();
        $registrationData['terms'] = (bool) $form->get('terms')->getData();

        return $this->render('ui_portal/auth-register-basic.html.twig', [
            'active_menu' => 'auth-register',
            'registration' => $registrationData,
            'registrationErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/auth/forgot-password', name: 'auth_forgot_password', methods: ['GET', 'POST'])]
    public function authForgotPassword(
        Request $request,
        PasswordResetService $passwordResetService
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('portal_account');
        }

        $state = ['email' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $state['email'] = trim((string) $request->request->get('email'));

            if (!filter_var($state['email'], FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please enter a valid email address.');
            } else {
                $passwordResetService->requestReset($state['email']);

                $this->addFlash('success', 'If the email exists, we just sent password reset instructions.');

                return $this->redirectToRoute('portal_auth_forgot_password');
            }
        }

        return $this->render('ui_portal/auth-forgot-password-basic.html.twig', [
            'active_menu' => 'auth-forgot-password',
            'forgot' => $state,
        ]);
    }

    #[Route('/auth/reset-password', name: 'auth_reset_password', methods: ['GET', 'POST'])]
    public function authResetPassword(Request $request, PasswordResetService $passwordResetService): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('portal_account');
        }

        $state = [
            'token' => trim((string) $request->query->get('token', '')),
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reset_password', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $state['token'] = trim((string) $request->request->get('token', ''));
            $password = (string) $request->request->get('password', '');
            $confirm = (string) $request->request->get('password_confirm', '');

            if ($state['token'] === '') {
                $this->addFlash('error', 'Reset token is missing. Use the link from your email.');
            } elseif (mb_strlen($password) < 8) {
                $this->addFlash('error', 'Password must be at least 8 characters long.');
            } elseif ($password !== $confirm) {
                $this->addFlash('error', 'Passwords do not match.');
            } else {
                if ($passwordResetService->resetPassword($state['token'], $password)) {
                    $this->addFlash('success', 'Password updated successfully. You can now sign in.');

                    return $this->redirectToRoute('portal_auth_login');
                }

                $this->addFlash('error', 'Reset link is invalid or has expired.');
            }
        }

        return $this->render('ui_portal/auth-reset-password.html.twig', [
            'active_menu' => 'auth-reset-password',
            'state' => $state,
        ]);
    }

    #[Route('/auth/resend-verification', name: 'auth_resend_verification', methods: ['GET', 'POST'])]
    public function authResendVerification(Request $request, EmailVerificationService $emailVerificationService): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('portal_dashboard');
        }

        $state = ['identifier' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('resend_verification', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $state['identifier'] = trim((string) $request->request->get('identifier', ''));

            if ($state['identifier'] === '') {
                $this->addFlash('error', 'Please enter the email tied to your account.');
            } else {
                $emailVerificationService->resendForEmail($state['identifier']);

                $this->addFlash('success', 'If your account exists and isn\'t verified, a fresh link is on the way.');

                return $this->redirectToRoute('portal_auth_resend_verification');
            }
        }

        return $this->render('ui_portal/auth-resend-verification.html.twig', [
            'active_menu' => 'auth-resend-verification',
            'state' => $state,
        ]);
    }

    #[Route('/auth/verify/{token}', name: 'auth_verify', methods: ['GET'])]
    public function authVerify(string $token, EmailVerificationService $emailVerificationService): Response
    {
        if ($emailVerificationService->verifyToken($token)) {
            $this->addFlash('success', 'Email verified! You can now sign in.');
        } else {
            $this->addFlash('error', 'This verification link is invalid or has expired.');
        }

        return $this->redirectToRoute('portal_auth_login');
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

    private function handlePasswordChange(
        Request $request,
        User $user,
        UserManager $userManager,
        AuditTrailService $auditTrailService,
        NotificationService $notificationService
    ): Response {
        if (!$this->isCsrfTokenValid('account_password', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $current = (string) $request->request->get('currentPassword', '');
        $new = (string) $request->request->get('newPassword', '');
        $confirm = (string) $request->request->get('confirmPassword', '');

        $errors = [];
        if ($current === '' || $new === '' || $confirm === '') {
            $errors[] = 'All password fields are required.';
        }
        if ($new !== '' && mb_strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }
        if ($new === $current && $new !== '') {
            $errors[] = 'New password must be different from the current password.';
        }
        if ($new !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToRoute('portal_account');
        }

        try {
            $userManager->changePassword($user, $current, $new);
        } catch (LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
            return $this->redirectToRoute('portal_account');
        }

        $auditTrailService->record($user, 'user.password.changed');
        $notificationService->sendAccountNotification($user, 'password_changed');

        $this->addFlash('success', 'Password updated successfully.');
        return $this->redirectToRoute('portal_account');
    }

    /**
     * @return array{0: bool, 1: list<string>}
     */
    private function handleAvatarMutation(Request $request, User $user, AvatarStorage $avatarStorage): array
    {
        $errors = [];
        $changed = false;
        $apiAvatarUrl = trim((string) $request->request->get('avatar_api_url', ''));

        $removeRequested = (bool) $request->request->get('avatar_remove');
        if ($removeRequested) {
            if ($user->getAvatarPath() !== null) {
                if (!$this->isExternalUrl($user->getAvatarPath())) {
                    $avatarStorage->remove($user->getAvatarPath());
                }
                $user->setAvatarPath(null);
                $user->setUpdatedAt(new DateTimeImmutable());
                $changed = true;
            }

            return [$changed, $errors];
        }

        $uploadedAvatar = $request->files->get('avatar');
        if ($uploadedAvatar instanceof UploadedFile) {
            if (!$uploadedAvatar->isValid()) {
                $errors[] = 'Upload failed, please try again.';
            } else {
                $size = $uploadedAvatar->getSize();
                $mime = $uploadedAvatar->getMimeType();
                if ($size !== null && $size > 2_000_000) {
                    $errors[] = 'Avatar must be smaller than 2 MB.';
                } elseif ($mime !== null && !in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                    $errors[] = 'Only JPG, PNG, GIF, or WebP files are allowed.';
                } else {
                    if (!$this->isExternalUrl($user->getAvatarPath())) {
                        $avatarStorage->remove($user->getAvatarPath());
                    }
                    $relativePath = $avatarStorage->store($uploadedAvatar);
                    $user->setAvatarPath($relativePath);
                    $user->setUpdatedAt(new DateTimeImmutable());
                    $changed = true;
                }
            }
        }

        if ($apiAvatarUrl !== '' && $this->isAllowedAvatarApiUrl($apiAvatarUrl)) {
            if (!$this->isExternalUrl($user->getAvatarPath())) {
                $avatarStorage->remove($user->getAvatarPath());
            }
            $user->setAvatarPath($apiAvatarUrl);
            $user->setUpdatedAt(new DateTimeImmutable());
            $changed = true;
        } elseif ($apiAvatarUrl !== '') {
            $errors[] = 'Selected avatar source is not allowed.';
        }

        return [$changed, $errors];
    }

    private function isAllowedAvatarApiUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if ($scheme !== 'https') {
            return false;
        }

        if ($host !== self::DICEBEAR_HOST) {
            return false;
        }

        return str_starts_with($path, '/9.x/') && str_ends_with($path, '/svg');
    }

    private function isExternalUrl(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function notificationRows(): array
    {
        return [
            ['key' => 'new_for_you', 'label' => 'New for you'],
            ['key' => 'account_activity', 'label' => 'Account activity'],
            ['key' => 'new_browser', 'label' => 'A new browser used to sign in'],
            ['key' => 'new_device', 'label' => 'A new device is linked'],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $submitted
     * @return array<string, array<string, bool>>
     */
    private function normalizeNotificationMatrix(array $submitted): array
    {
        $matrix = self::DEFAULT_NOTIFICATION_MATRIX;

        foreach ($matrix as $type => $channels) {
            foreach ($channels as $channel => $enabled) {
                $matrix[$type][$channel] = isset($submitted[$type][$channel]);
            }
        }

        return $matrix;
    }

    private function notificationTypeForKey(string $key): string
    {
        if (str_contains($key, 'browser')) {
            return 'new_browser';
        }

        if (str_contains($key, 'device')) {
            return 'new_device';
        }

        if (in_array($key, ['welcome', 'family_created', 'family_joined', 'preferences_updated', 'notifications_updated'], true)) {
            return 'new_for_you';
        }

        return 'account_activity';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{title: string, body: string}
     */
    private function notificationSummary(string $key, array $payload): array
    {
        if ($key === 'verify_email') {
            return [
                'title' => 'Verification requise',
                'body' => 'Confirmez votre adresse e-mail HomeBalance.',
            ];
        }

        if ($key === 'reset_requested') {
            return [
                'title' => 'Demande de reinitialisation',
                'body' => 'Un lien de reinitialisation vient d etre genere.',
            ];
        }

        if ($key === 'password_changed' || $key === 'password_reset') {
            return [
                'title' => 'Mot de passe mis a jour',
                'body' => 'Le mot de passe du compte a ete modifie.',
            ];
        }

        if (str_starts_with($key, 'family_')) {
            return [
                'title' => 'Mise a jour famille',
                'body' => 'Votre foyer a recu une nouvelle mise a jour.',
            ];
        }

        if ($key === 'profile_updated') {
            $fields = isset($payload['fields']) && is_array($payload['fields']) ? count($payload['fields']) : null;

            return [
                'title' => 'Profil mis a jour',
                'body' => $fields ? sprintf('%d champ(s) profil modifies.', $fields) : 'Vos informations de profil ont ete mises a jour.',
            ];
        }

        return [
            'title' => 'Notification HomeBalance',
            'body' => 'Une nouvelle activite est disponible sur votre compte.',
        ];
    }
    private function buildAccountFormData(User $user): array
    {
        $profile = $user->getPreferences()['profile'] ?? [];

        return [
            'firstName' => $user->getFirstName() ?? '',
            'lastName' => $user->getLastName() ?? '',
            'email' => $user->getEmail() ?? '',
            'organization' => $profile['organization'] ?? '',
            'phoneNumber' => $profile['phoneNumber'] ?? '',
            'address' => $profile['address'] ?? '',
            'state' => $profile['state'] ?? '',
            'zipCode' => $profile['zipCode'] ?? '',
            'country' => $profile['country'] ?? '',
            'language' => $user->getLocale() ?? 'en',
            'timeZones' => $user->getTimeZone() ?? 'UTC',
            'currency' => $profile['currency'] ?? 'usd',
            'avatarPath' => $user->getAvatarPath(),
        ];
    }
}



