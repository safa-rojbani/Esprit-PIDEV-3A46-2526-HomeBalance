<?php

namespace App\Service;

use App\Entity\Family;
use App\Entity\User;

final class AuditNotifier
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordAndNotify(
        User $subject,
        string $action,
        string $notificationKey,
        array $payload = [],
        ?Family $family = null,
        ?User $actor = null,
    ): void {
        if ($actor !== null) {
            $payload['actor'] = $actor->getUserIdentifier();
        }

        $this->auditTrailService->record($subject, $action, $payload, $family);
        $this->notificationService->sendAccountNotification($subject, $notificationKey, $payload);
    }
}
