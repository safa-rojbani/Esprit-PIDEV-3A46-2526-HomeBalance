<?php

namespace App\Service\Ai;

use App\Entity\AdminAiExecutionLog;
use App\Entity\AdminAiSession;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\AdminAiExecutionLogRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminIntentExecutionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly AdminIntentDryRunService $dryRunService,
        private readonly NotificationService $notificationService,
        private readonly AdminIntentAuditService $auditService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AdminAiExecutionLogRepository $executionLogRepository,
    ) {
    }

    /**
     * @return array{result: string, executedActionsCount: int, message: string, meta: array<string, mixed>}
     */
    public function execute(AdminAiSession $session, User $actor): array
    {
        if ($session->isExpired()) {
            return $this->logAndReturn($session, $actor, AdminAiExecutionLog::RESULT_BLOCKED, 0, 'Session expired.', []);
        }

        $intent = $session->getNormalizedIntent();
        $name = (string) ($intent['intent'] ?? '');
        $preview = $this->dryRunService->buildPreview($intent);
        $targets = $preview['affectedUsers'] ?? [];
        if (!is_array($targets)) {
            $targets = [];
        }

        $executed = 0;
        $meta = [
            'intent' => $name,
            'targetCount' => count($targets),
            'maxImpact' => AdminIntentCatalog::maxImpact($name),
            'failures' => [],
        ];

        if (AdminIntentCatalog::maxImpact($name) > 0 && count($targets) > AdminIntentCatalog::maxImpact($name)) {
            return $this->logAndReturn(
                $session,
                $actor,
                AdminAiExecutionLog::RESULT_BLOCKED,
                0,
                sprintf('Guardrail blocked execution: target count (%d) exceeds max impact (%d).', count($targets), AdminIntentCatalog::maxImpact($name)),
                $meta,
            );
        }

        if ($name === AdminIntentCatalog::INTENT_BULK_SUSPEND_USERS) {
            $executed = $this->executeBulkStatusUpdate($targets, $actor, $session, UserStatus::SUSPENDED, 'admin.ai.bulk_suspend.executed', $meta);
        } elseif ($name === AdminIntentCatalog::INTENT_BULK_REACTIVATE_USERS) {
            $executed = $this->executeBulkStatusUpdate($targets, $actor, $session, UserStatus::ACTIVE, 'admin.ai.bulk_reactivate.executed', $meta);
        } elseif ($name === AdminIntentCatalog::INTENT_RESET_USER_PASSWORD) {
            $userId = $this->resolveTargetUserId($intent);
            $user = $this->userRepository->find($userId);
            if ($user instanceof User) {
                $user
                    ->setResetToken(bin2hex(random_bytes(32)))
                    ->setResetExpiresAt((new DateTimeImmutable())->modify('+1 hour'))
                    ->setUpdatedAt(new DateTimeImmutable());
                $this->notificationService->sendAccountNotification($user, 'reset_requested');
                $this->auditService->record($actor, 'admin.ai.password_reset.executed', [
                    'targetUserId' => $user->getId(),
                    'sessionId' => $session->getId(),
                ]);
                $executed = 1;
            } else {
                $meta['failures'][] = [
                    'targetUserId' => $userId,
                    'reason' => 'Target user not found.',
                ];
            }
        } elseif ($name === AdminIntentCatalog::INTENT_EXPORT_AUDIT_FOR_USER) {
            $userId = $this->resolveTargetUserId($intent);
            $user = $this->userRepository->find($userId);
            if ($user instanceof User) {
                $meta['downloadUrl'] = $this->urlGenerator->generate('portal_admin_users_audit_export', [
                    'id' => $user->getId(),
                ]);
                $executed = 1;
                $this->auditService->record($actor, 'admin.ai.audit_export.prepared', [
                    'targetUserId' => $user->getId(),
                    'sessionId' => $session->getId(),
                ]);
            } else {
                $meta['failures'][] = [
                    'targetUserId' => $userId,
                    'reason' => 'Target user not found.',
                ];
            }
        } elseif ($name === AdminIntentCatalog::INTENT_SEARCH_USERS_WITH_RISK_FILTERS) {
            $executed = 0;
        } else {
            return $this->logAndReturn($session, $actor, AdminAiExecutionLog::RESULT_FAILED, 0, 'Unsupported intent.', []);
        }

        $failures = $meta['failures'];
        if (!is_array($failures)) {
            $failures = [];
        }
        $failureCount = count($failures);
        $result = AdminAiExecutionLog::RESULT_SUCCESS;
        $message = 'Execution completed.';

        if ($failureCount > 0 && $executed > 0) {
            $result = AdminAiExecutionLog::RESULT_PARTIAL;
            $message = sprintf('Execution partially completed (%d success, %d failed).', $executed, $failureCount);
            $meta['rollbackReport'] = [
                'mode' => 'best_effort',
                'description' => 'Only successful actions were persisted; failed targets were skipped.',
            ];
        } elseif ($failureCount > 0 && $executed === 0) {
            $result = AdminAiExecutionLog::RESULT_FAILED;
            $message = sprintf('Execution failed (%d failed targets).', $failureCount);
            $meta['rollbackReport'] = [
                'mode' => 'not_applicable',
                'description' => 'No mutation persisted.',
            ];
        }

        $session
            ->setStatus(AdminAiSession::STATUS_EXECUTED)
            ->touch();
        $this->entityManager->flush();

        return $this->logAndReturn(
            $session,
            $actor,
            $result,
            $executed,
            $message,
            $meta,
        );
    }

    /**
     * @param array<string, mixed> $target
     */
    private function loadTargetUser(array $target): ?User
    {
        $id = isset($target['id']) ? (string) $target['id'] : '';
        if ($id === '') {
            return null;
        }

        $user = $this->userRepository->find($id);

        return $user instanceof User ? $user : null;
    }

    /**
     * @param list<array<string, mixed>> $targets
     * @param array<string, mixed> $meta
     */
    private function executeBulkStatusUpdate(
        array $targets,
        User $actor,
        AdminAiSession $session,
        UserStatus $toStatus,
        string $auditAction,
        array &$meta,
    ): int {
        $executed = 0;

        foreach ($targets as $target) {
            $user = $this->loadTargetUser($target);
            if (!$user instanceof User) {
                $meta['failures'][] = [
                    'targetUserId' => (string) ($target['id'] ?? ''),
                    'reason' => 'User not found.',
                ];

                continue;
            }

            if ($user->getStatus() === $toStatus) {
                continue;
            }

            try {
                $user->setStatus($toStatus)->setUpdatedAt(new DateTimeImmutable());
                $this->notificationService->sendAccountNotification($user, 'status_changed', ['status' => strtoupper($toStatus->value)]);
                $this->auditService->record($actor, $auditAction, [
                    'targetUserId' => $user->getId(),
                    'sessionId' => $session->getId(),
                ]);
                $executed++;
            } catch (\Throwable $exception) {
                $meta['failures'][] = [
                    'targetUserId' => $user->getId(),
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return $executed;
    }

    /**
     * @param array<string, mixed> $intent
     */
    private function resolveTargetUserId(array $intent): string
    {
        $userId = trim((string) ($intent['user_id'] ?? ''));
        if ($userId !== '') {
            return $userId;
        }

        $filters = $intent['filters'] ?? [];
        if (!is_array($filters)) {
            return '';
        }

        $email = $filters['email'] ?? null;
        if (is_string($email) && trim($email) !== '') {
            $user = $this->userRepository->findOneBy(['email' => trim($email)]);

            return $user instanceof User ? $user->getId() : '';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{result: string, executedActionsCount: int, message: string, meta: array<string, mixed>}
     */
    private function logAndReturn(
        AdminAiSession $session,
        User $actor,
        string $result,
        int $executedActionsCount,
        string $message,
        array $meta,
    ): array {
        $log = (new AdminAiExecutionLog())
            ->setSession($session)
            ->setActorUser($actor)
            ->setResult($result)
            ->setExecutedActionsCount($executedActionsCount)
            ->setErrorSummary($result === AdminAiExecutionLog::RESULT_SUCCESS ? null : $message);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return [
            'result' => $result,
            'executedActionsCount' => $executedActionsCount,
            'message' => $message,
            'meta' => $meta,
        ];
    }
}
