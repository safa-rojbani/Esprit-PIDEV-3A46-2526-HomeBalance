<?php

namespace App\Service\Ai;

use App\Entity\User;
use App\Service\AuditTrailService;

final class AdminIntentAuditService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function record(User $actor, string $action, array $context = []): void
    {
        $this->auditTrailService->record($actor, $action, $context, $actor->getFamily());
    }
}

