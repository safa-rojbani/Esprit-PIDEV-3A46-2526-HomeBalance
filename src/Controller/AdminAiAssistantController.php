<?php

namespace App\Controller;

use App\Entity\AdminAiSession;
use App\Entity\User;
use App\Repository\AdminBiometricProfileRepository;
use App\Repository\AdminAiExecutionLogRepository;
use App\Repository\AdminAiSessionRepository;
use App\Service\Ai\AdminIntentAuditService;
use App\Service\Ai\AdminIntentAuthorizationService;
use App\Service\Ai\AdminIntentCatalog;
use App\Service\Ai\AdminIntentDryRunService;
use App\Service\Ai\AdminIntentExecutionService;
use App\Service\Ai\AdminIntentLlmService;
use App\Service\Ai\AdminIntentValidatorService;
use App\Service\Security\StepUpGuardService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminAiAssistantController extends AbstractController
{
    public function __construct(
        private readonly bool $featureAiAssistantEnabled,
        private readonly bool $featureAiExecuteEnabled,
    ) {
    }

    #[Route('/portal/admin/console/security-ai', name: 'portal_admin_console_security_ai', methods: ['GET'])]
    public function page(
        Request $request,
        AdminAiSessionRepository $sessionRepository,
        AdminAiExecutionLogRepository $executionLogRepository,
        AdminBiometricProfileRepository $biometricProfileRepository,
        StepUpGuardService $stepUpGuardService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertAssistantEnabled();

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Invalid actor.');
        }

        $queryActionKey = trim((string) $request->query->get('action_key', ''));
        $queryTargetUser = trim((string) $request->query->get('target_user_id', ''));
        $queryReturnTo = trim((string) $request->query->get('return_to', ''));
        $step = $this->normalizeWizardStep((string) $request->query->get('step', 'plan'));
        $sessionId = trim((string) $request->query->get('session_id', ''));

        $selectedSession = null;
        if ($sessionId !== '') {
            $session = $sessionRepository->find($sessionId);
            if ($session instanceof AdminAiSession && $session->getActorUser()?->getId() === $actor->getId()) {
                $selectedSession = $session;
            }
        }

        $sessionLogs = [];
        if ($selectedSession instanceof AdminAiSession) {
            $sessionLogs = $executionLogRepository->findForSession($selectedSession);
        }

        $biometricProfile = $biometricProfileRepository->findEnabledForUser($actor);
        $biometricEnrolled = $biometricProfile !== null && $biometricProfile->getProvider() === 'luxand';
        $biometricNeedsMigration = $biometricProfile !== null && $biometricProfile->getProvider() !== 'luxand';

        $impactSummary = $this->buildImpactSummary($selectedSession);
        // For AI execution, step-up verification is bound to action-level approval, not per-target user.
        $sessionTargetUserId = '';
        $stepUpTargetUser = null;
        $stepUpActionKey = 'admin.ai.execute';
        $stepUpAlreadySatisfied = $stepUpGuardService->isSatisfied($request, $stepUpActionKey, $stepUpTargetUser);
        $stepUpReturnTo = $this->generateUrl('portal_admin_console_security_ai', [
            'step' => 'confirm',
            'session_id' => $selectedSession?->getId(),
        ]);

        return $this->render('ui_portal/admin/console/security_ai.html.twig', [
            'active_menu' => 'admin-security-ai',
            'consoleSection' => 'security_ai',
            'recentAiSessions' => $sessionRepository->findRecentForActor($actor, 10),
            'wizardStep' => $step,
            'selectedSession' => $selectedSession,
            'selectedSessionLogs' => $sessionLogs,
            'biometricEnrolled' => $biometricEnrolled,
            'biometricNeedsMigration' => $biometricNeedsMigration,
            'biometricProfile' => $biometricProfile,
            'impactSummary' => $impactSummary,
            'sessionTargetUserId' => $sessionTargetUserId,
            'stepUpActionKey' => $stepUpActionKey,
            'stepUpAlreadySatisfied' => $stepUpAlreadySatisfied,
            'stepUpReturnTo' => $stepUpReturnTo,
            'verifyActionKey' => $queryActionKey,
            'verifyTargetUserId' => $queryTargetUser,
            'verifyReturnTo' => $queryReturnTo,
        ]);
    }

    #[Route('/portal/admin/ai/plan', name: 'portal_admin_ai_plan', methods: ['POST'])]
    public function plan(
        Request $request,
        EntityManagerInterface $entityManager,
        AdminIntentAuthorizationService $authorizationService,
        AdminIntentLlmService $llmService,
        AdminIntentValidatorService $validatorService,
        AdminIntentAuditService $auditService,
        LoggerInterface $logger,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertAssistantEnabled();

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Invalid actor.');
        }
        $authorizationService->assertAdmin($actor);

        $payload = $this->payload($request);
        $prompt = $this->sanitizePrompt((string) ($payload['prompt'] ?? ''));
        if ($prompt === '') {
            return new JsonResponse([
                'ok' => false,
                'error' => 'prompt is required.',
            ], 422);
        }

        $rawIntent = $llmService->planFromPrompt($prompt);
        $validated = $validatorService->validate($rawIntent);
        $validationErrors = $validated['errors'];

        if ($this->isAmbiguousPrompt($prompt, $validated['normalized'])) {
            $validationErrors[] = 'Ambiguous request detected.';
            $validated['valid'] = false;
            $validated['errors'] = $validationErrors;
        }

        $normalized = $validated['normalized'];
        $intentName = (string) ($normalized['intent'] ?? '');
        $requiresStepUp = AdminIntentCatalog::isDangerous($intentName);

        $session = (new AdminAiSession())
            ->setActorUser($actor)
            ->setRawPrompt($prompt)
            ->setNormalizedIntent($normalized)
            ->setStatus($validated['valid'] ? AdminAiSession::STATUS_PLANNED : AdminAiSession::STATUS_REJECTED)
            ->setRequiresStepUp($requiresStepUp)
            ->touch();

        $entityManager->persist($session);
        $entityManager->flush();

        $auditService->record($actor, 'admin.ai.plan.created', [
            'sessionId' => $session->getId(),
            'intent' => $intentName,
            'valid' => $validated['valid'],
            'fallbackUsed' => (bool) ($normalized['fallback_used'] ?? false),
        ]);
        $logger->info('admin.ai.plan.validation', [
            'sessionId' => $session->getId(),
            'prompt' => $prompt,
            'raw_intent' => $rawIntent,
            'normalized_intent' => $normalized,
            'errors' => $validated['errors'],
        ]);

        if (!$validated['valid']) {
            return new JsonResponse([
                'ok' => false,
                'session_id' => $session->getId(),
                'status' => $session->getStatus(),
                'requires_step_up' => $requiresStepUp,
                'valid' => false,
                'errors' => $validated['errors'],
                'user_message' => $validatorService->buildUserFriendlyMessage($validated['errors']),
                'intent' => $normalized,
            ], 422);
        }

        return new JsonResponse([
            'ok' => true,
            'session_id' => $session->getId(),
            'status' => $session->getStatus(),
            'requires_step_up' => $requiresStepUp,
            'valid' => true,
            'errors' => [],
            'user_message' => 'Plan generated successfully. Review and run dry-run.',
            'intent' => $normalized,
        ]);
    }

    #[Route('/portal/admin/ai/dry-run', name: 'portal_admin_ai_dry_run', methods: ['POST'])]
    public function dryRun(
        Request $request,
        EntityManagerInterface $entityManager,
        AdminAiSessionRepository $sessionRepository,
        AdminIntentAuthorizationService $authorizationService,
        AdminIntentDryRunService $dryRunService,
        AdminIntentAuditService $auditService,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertAssistantEnabled();

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Invalid actor.');
        }
        $authorizationService->assertAdmin($actor);

        $payload = $this->payload($request);
        $sessionId = trim((string) ($payload['session_id'] ?? ''));
        if ($sessionId === '') {
            return new JsonResponse(['ok' => false, 'error' => 'session_id is required.'], 422);
        }

        $session = $sessionRepository->find($sessionId);
        if (!$session instanceof AdminAiSession || $session->getActorUser()?->getId() !== $actor->getId()) {
            return new JsonResponse(['ok' => false, 'error' => 'Session not found.'], 404);
        }

        if ($session->isExpired()) {
            $session->setStatus(AdminAiSession::STATUS_EXPIRED)->touch();
            $entityManager->flush();

            return new JsonResponse(['ok' => false, 'error' => 'Session expired.'], 409);
        }
        if ($session->getStatus() === AdminAiSession::STATUS_REJECTED) {
            return new JsonResponse(['ok' => false, 'error' => 'Session is rejected and cannot run dry-run.'], 409);
        }

        $preview = $dryRunService->buildPreview($session->getNormalizedIntent());
        $session
            ->setDryRunSnapshot($preview)
            ->setStatus(AdminAiSession::STATUS_DRY_RUN_READY)
            ->touch();
        $entityManager->flush();

        $auditService->record($actor, 'admin.ai.dry_run.completed', [
            'sessionId' => $session->getId(),
            'intent' => (string) ($session->getNormalizedIntent()['intent'] ?? ''),
            'count' => (int) ($preview['count'] ?? 0),
        ]);

        $confirmToken = bin2hex(random_bytes(24));
        $request->getSession()->set($this->confirmSessionKey($session->getId()), $confirmToken);

        return new JsonResponse([
            'ok' => true,
            'session_id' => $session->getId(),
            'status' => $session->getStatus(),
            'confirm_token' => $confirmToken,
            'preview' => $preview,
        ]);
    }

    #[Route('/portal/admin/ai/execute', name: 'portal_admin_ai_execute', methods: ['POST'])]
    public function execute(
        Request $request,
        AdminAiSessionRepository $sessionRepository,
        AdminIntentAuthorizationService $authorizationService,
        AdminIntentExecutionService $executionService,
        AdminIntentAuditService $auditService,
        StepUpGuardService $stepUpGuardService,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertAssistantEnabled();

        if (!$this->featureAiExecuteEnabled) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'AI execution is disabled by feature flag.',
            ], 423);
        }

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Invalid actor.');
        }
        $authorizationService->assertAdmin($actor);

        $payload = $this->payload($request);
        $sessionId = trim((string) ($payload['session_id'] ?? ''));
        $confirmToken = (string) ($payload['confirm_token'] ?? '');
        if ($sessionId === '' || $confirmToken === '') {
            return new JsonResponse(['ok' => false, 'error' => 'session_id and confirm_token are required.'], 422);
        }

        $expectedConfirmToken = (string) $request->getSession()->get($this->confirmSessionKey($sessionId), '');
        if ($expectedConfirmToken === '' || !hash_equals($expectedConfirmToken, $confirmToken)) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid confirmation token.'], 403);
        }

        $session = $sessionRepository->find($sessionId);
        if (!$session instanceof AdminAiSession || $session->getActorUser()?->getId() !== $actor->getId()) {
            return new JsonResponse(['ok' => false, 'error' => 'Session not found.'], 404);
        }

        if ($session->isExpired()) {
            return new JsonResponse(['ok' => false, 'error' => 'Session expired.'], 409);
        }
        if (!in_array($session->getStatus(), [AdminAiSession::STATUS_DRY_RUN_READY, AdminAiSession::STATUS_PLANNED], true)) {
            return new JsonResponse(['ok' => false, 'error' => 'Session is not executable in current state.'], 409);
        }

        if (!$stepUpGuardService->isSatisfied($request, 'admin.ai.execute')) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Step-up verification is mandatory before execution.',
                'step_up_required' => true,
            ], 423);
        }

        $request->getSession()->remove($this->confirmSessionKey($sessionId));

        $result = $executionService->execute($session, $actor);
        $auditService->record($actor, 'admin.ai.execute.completed', [
            'sessionId' => $session->getId(),
            'result' => $result['result'] ?? 'FAILED',
            'executedActionsCount' => (int) ($result['executedActionsCount'] ?? 0),
        ]);

        return new JsonResponse([
            'ok' => true,
            'session_id' => $session->getId(),
            'result' => $result,
        ]);
    }

    #[Route('/portal/admin/ai/sessions/{id}', name: 'portal_admin_ai_session', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function sessionState(
        string $id,
        AdminAiSessionRepository $sessionRepository,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->assertAssistantEnabled();

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Invalid actor.');
        }

        $session = $sessionRepository->find($id);
        if (!$session instanceof AdminAiSession || $session->getActorUser()?->getId() !== $actor->getId()) {
            return new JsonResponse(['ok' => false, 'error' => 'Session not found.'], 404);
        }

        return new JsonResponse([
            'ok' => true,
            'session' => [
                'id' => $session->getId(),
                'status' => $session->getStatus(),
                'requires_step_up' => $session->isRequiresStepUp(),
                'intent' => $session->getNormalizedIntent(),
                'dry_run_snapshot' => $session->getDryRunSnapshot(),
                'expires_at' => $session->getExpiresAt()?->format(DATE_ATOM),
                'created_at' => $session->getCreatedAt()->format(DATE_ATOM),
                'updated_at' => $session->getUpdatedAt()->format(DATE_ATOM),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $contentType = (string) $request->headers->get('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) $request->getContent(), true);

            return is_array($decoded) ? $decoded : [];
        }

        return $request->request->all();
    }

    private function assertAssistantEnabled(): void
    {
        if (!$this->featureAiAssistantEnabled) {
            throw $this->createNotFoundException('AI assistant feature is disabled.');
        }
    }

    private function confirmSessionKey(string $sessionId): string
    {
        return 'ai_execute_confirm_' . $sessionId;
    }

    private function sanitizePrompt(string $prompt): string
    {
        $prompt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $prompt) ?? '';
        $prompt = trim($prompt);

        if (mb_strlen($prompt) > 2000) {
            $prompt = mb_substr($prompt, 0, 2000);
        }

        return $prompt;
    }

    /**
     * @param array<string, mixed> $normalizedIntent
     */
    private function isAmbiguousPrompt(string $prompt, array $normalizedIntent): bool
    {
        $promptLower = mb_strtolower($prompt);
        $ambiguousPatterns = [
            'do something',
            'handle users',
            'manage users',
            'fix users',
        ];
        $isAmbiguousText = false;
        foreach ($ambiguousPatterns as $pattern) {
            if (str_contains($promptLower, $pattern)) {
                $isAmbiguousText = true;
                break;
            }
        }

        if (!$isAmbiguousText) {
            return false;
        }

        $intent = (string) ($normalizedIntent['intent'] ?? '');
        $filters = $normalizedIntent['filters'] ?? [];
        if (!is_array($filters)) {
            $filters = [];
        }

        return $intent === AdminIntentCatalog::INTENT_SEARCH_USERS_WITH_RISK_FILTERS && count($filters) <= 1;
    }

    private function normalizeWizardStep(string $step): string
    {
        $allowedSteps = ['plan', 'dry-run', 'confirm', 'step-up', 'done'];
        if (!in_array($step, $allowedSteps, true)) {
            return 'plan';
        }

        return $step;
    }

    private function buildImpactSummary(?AdminAiSession $session): string
    {
        if (!$session instanceof AdminAiSession) {
            return 'No AI session selected yet.';
        }

        $intent = $session->getNormalizedIntent();
        $intentName = (string) ($intent['intent'] ?? 'unknown');
        $preview = $session->getDryRunSnapshot();
        $affectedUsers = $preview['affectedUsers'] ?? [];
        if (!is_array($affectedUsers)) {
            $affectedUsers = [];
        }

        $count = (int) ($preview['count'] ?? count($affectedUsers));
        $sampleNames = [];
        foreach (array_slice($affectedUsers, 0, 3) as $userRow) {
            if (!is_array($userRow)) {
                continue;
            }
            $label = trim((string) ($userRow['email'] ?? $userRow['username'] ?? $userRow['id'] ?? ''));
            if ($label !== '') {
                $sampleNames[] = $label;
            }
        }

        $sampleText = $sampleNames !== [] ? (' Sample: ' . implode(', ', $sampleNames) . '.') : '';

        return match ($intentName) {
            AdminIntentCatalog::INTENT_BULK_SUSPEND_USERS => sprintf('You will suspend %d user(s).%s', $count, $sampleText),
            AdminIntentCatalog::INTENT_BULK_REACTIVATE_USERS => sprintf('You will reactivate %d user(s).%s', $count, $sampleText),
            AdminIntentCatalog::INTENT_RESET_USER_PASSWORD => sprintf('You will reset password for %d user(s).%s', $count, $sampleText),
            AdminIntentCatalog::INTENT_EXPORT_AUDIT_FOR_USER => sprintf('You will prepare audit export for %d user(s).%s', $count, $sampleText),
            AdminIntentCatalog::INTENT_SEARCH_USERS_WITH_RISK_FILTERS => sprintf('You will run a risk search returning up to %d user(s).%s', $count, $sampleText),
            default => sprintf('You will execute intent "%s" on %d user(s).%s', $intentName, $count, $sampleText),
        };
    }

    private function resolveSessionTargetUserId(?AdminAiSession $session): string
    {
        if (!$session instanceof AdminAiSession) {
            return '';
        }

        $intent = $session->getNormalizedIntent();
        $targetUserId = trim((string) ($intent['user_id'] ?? ''));
        if ($targetUserId !== '') {
            return $targetUserId;
        }

        $preview = $session->getDryRunSnapshot();
        $affectedUsers = $preview['affectedUsers'] ?? [];
        if (!is_array($affectedUsers) || $affectedUsers === []) {
            return '';
        }

        $first = $affectedUsers[0] ?? null;
        if (!is_array($first)) {
            return '';
        }

        return trim((string) ($first['id'] ?? ''));
    }
}
