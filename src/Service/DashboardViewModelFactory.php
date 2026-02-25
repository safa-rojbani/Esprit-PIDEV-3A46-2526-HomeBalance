<?php

namespace App\Service;

use App\Entity\User;

final class DashboardViewModelFactory
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
        private readonly UserMetricsFormatter $metricsFormatter,
    ) {
    }

    /**
     * @return array{
     *     metrics: array<string, mixed>,
     *     recentActivity: list<\App\DTO\AuditEvent>,
     *     engagement: array{score: int, familyName: ?string, delivery: string, lastLogin: ?\DateTime}
     * }
     */
    public function build(User $user): array
    {
        $recentActivity = $this->auditTrailService->recentForUser($user, 5);
        $metrics = $this->metricsFormatter->summarize($user);

        $preferences = $user->getPreferences() ?? [];
        $notificationDelivery = $preferences['notifications']['delivery'] ?? 'online';

        $engagementScore = min(
            100,
            max(
                12,
                ($metrics['badgeCount'] ?? 0) * 12 + count($recentActivity) * 6 + ($metrics['familyMembers'] ?? 0) * 4
            )
        );

        $engagement = [
            'score' => $engagementScore,
            'familyName' => $metrics['familyName'] ?? null,
            'delivery' => $notificationDelivery,
            'lastLogin' => $user->getLastLogin(),
        ];

        return [
            'metrics' => $metrics,
            'recentActivity' => $recentActivity,
            'engagement' => $engagement,
        ];
    }
}
