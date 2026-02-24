<?php

namespace App\Service\Ai;

use App\Entity\User;
use App\Repository\AuditTrailRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;

final class AdminIntentDryRunService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AuditTrailRepository $auditTrailRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $normalizedIntent
     * @return array<string, mixed>
     */
    public function buildPreview(array $normalizedIntent): array
    {
        $intent = (string) ($normalizedIntent['intent'] ?? '');
        $limit = (int) ($normalizedIntent['limit'] ?? 50);

        if (in_array($intent, [
            AdminIntentCatalog::INTENT_BULK_SUSPEND_USERS,
            AdminIntentCatalog::INTENT_BULK_REACTIVATE_USERS,
            AdminIntentCatalog::INTENT_SEARCH_USERS_WITH_RISK_FILTERS,
        ], true)) {
            $users = $this->findUsersByFilters($normalizedIntent, $limit);

            return [
                'intent' => $intent,
                'count' => count($users),
                'affectedUsers' => $users,
            ];
        }

        if (in_array($intent, [
            AdminIntentCatalog::INTENT_RESET_USER_PASSWORD,
            AdminIntentCatalog::INTENT_EXPORT_AUDIT_FOR_USER,
        ], true)) {
            $userId = $this->resolveTargetUserId($normalizedIntent);
            if ($userId === '') {
                return [
                    'intent' => $intent,
                    'count' => 0,
                    'affectedUsers' => [],
                    'error' => 'A specific target is required (user_id or filters.email).',
                ];
            }

            $user = $this->userRepository->find($userId);
            if (!$user instanceof User) {
                return [
                    'intent' => $intent,
                    'count' => 0,
                    'affectedUsers' => [],
                    'error' => 'Target user not found.',
                ];
            }

            return [
                'intent' => $intent,
                'count' => 1,
                'affectedUsers' => [$this->presentUserRisk($user)],
            ];
        }

        return [
            'intent' => $intent,
            'count' => 0,
            'affectedUsers' => [],
            'error' => 'Unsupported intent.',
        ];
    }

    /**
     * @param array<string, mixed> $normalizedIntent
     * @return list<array<string, mixed>>
     */
    private function findUsersByFilters(array $normalizedIntent, int $limit): array
    {
        $filters = $normalizedIntent['filters'] ?? [];
        if (!is_array($filters)) {
            $filters = [];
        }

        $status = strtoupper((string) ($filters['status'] ?? 'ACTIVE'));
        if (!in_array($status, ['ACTIVE', 'SUSPENDED', 'ALL'], true)) {
            $status = 'ACTIVE';
        }

        $query = trim((string) ($filters['query'] ?? ''));
        $emailFilter = $filters['email'] ?? null;
        $failedDays = max(0, (int) ($filters['failed_logins_last_days'] ?? 30));
        $passwordDays = max(0, (int) ($filters['password_changes_last_days'] ?? 30));
        $minPasswordChanges = max(0, (int) ($filters['min_password_changes'] ?? 0));
        $lastActiveBeforeDays = max(0, (int) ($filters['last_active_before_days'] ?? 0));
        $riskScoreAbove = max(0, (int) ($filters['risk_score_above'] ?? 0));

        $qb = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.updatedAt', 'DESC')
            ->setMaxResults($limit * 3);

        if ($status !== 'ALL') {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', strtolower($status));
        }

        if ($query !== '') {
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.username) LIKE :q OR LOWER(u.FirstName) LIKE :q OR LOWER(u.LastName) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        if (is_string($emailFilter) && trim($emailFilter) !== '') {
            $qb->andWhere('LOWER(u.email) = :emailFilter')
                ->setParameter('emailFilter', mb_strtolower(trim($emailFilter)));
        } elseif (is_array($emailFilter) && $emailFilter !== []) {
            $emails = array_values(array_filter(array_map(static fn ($item): string => mb_strtolower(trim((string) $item)), $emailFilter), static fn (string $item): bool => $item !== ''));
            if ($emails !== []) {
                $qb->andWhere('LOWER(u.email) IN (:emailFilters)')
                    ->setParameter('emailFilters', $emails);
            }
        }

        /** @var list<User> $rawUsers */
        $rawUsers = $qb->getQuery()->getResult();

        $sinceFailed = (new DateTimeImmutable())->modify(sprintf('-%d days', $failedDays));
        $sincePassword = (new DateTimeImmutable())->modify(sprintf('-%d days', $passwordDays));
        $results = [];

        foreach ($rawUsers as $user) {
            $failedCount = $this->countActionsSince($user, ['auth.login.failed', 'user.login.failed'], $sinceFailed);
            $passwordCount = $this->countActionsSince($user, ['user.password.changed', 'user.reset.completed'], $sincePassword);

            if ($passwordCount < $minPasswordChanges) {
                continue;
            }

            if ($lastActiveBeforeDays > 0) {
                $cutoff = (new DateTimeImmutable())->modify(sprintf('-%d days', $lastActiveBeforeDays));
                $lastLogin = $user->getLastLogin();
                if ($lastLogin !== null && $lastLogin > $cutoff) {
                    continue;
                }
            }

            $row = $this->presentUserRisk($user);
            $row['failedLoginsWindow'] = $failedCount;
            $row['passwordChangesWindow'] = $passwordCount;
            $row['riskScore'] = min(100, ($failedCount * 10) + ($passwordCount * 5) + ($user->getStatus()?->value === 'suspended' ? 20 : 0));

            if ($riskScoreAbove > 0 && $row['riskScore'] < $riskScoreAbove) {
                continue;
            }

            $results[] = $row;

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    private function countActionsSince(User $user, array $actions, DateTimeImmutable $since): int
    {
        $em = $this->auditTrailRepository->getEntityManager();

        return (int) $em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from('App\Entity\AuditTrail', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('a.action IN (:actions)')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('actions', $actions)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentUserRisk(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'status' => $user->getStatus()?->value,
            'lastLogin' => $user->getLastLogin()?->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $normalizedIntent
     */
    private function resolveTargetUserId(array $normalizedIntent): string
    {
        $userId = trim((string) ($normalizedIntent['user_id'] ?? ''));
        if ($userId !== '') {
            return $userId;
        }

        $filters = $normalizedIntent['filters'] ?? [];
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
}
