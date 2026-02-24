<?php

namespace App\Service\Security;

final class StepUpPolicyService
{
    public const PASS_THRESHOLD = 82.0;
    public const FALLBACK_THRESHOLD = 65.0;

    /**
     * @var array<string, bool>
     */
    private array $protectedActions = [
        'admin.user.reset_password' => true,
        'admin.user.toggle_status' => true,
        'admin.user.anonymize' => true,
        'admin.user.role_change.approve' => true,
        'admin.user.role_change.reject' => true,
        'admin.user.audit_export' => true,
        'admin.ai.execute' => true,
    ];

    public function __construct(
        private readonly bool $featureEnabled,
    ) {
    }

    public function isStepUpRequired(string $actionKey): bool
    {
        if (!$this->featureEnabled) {
            return false;
        }

        return $this->protectedActions[$actionKey] ?? false;
    }

    public function passThreshold(): float
    {
        return self::PASS_THRESHOLD;
    }

    public function fallbackThreshold(): float
    {
        return self::FALLBACK_THRESHOLD;
    }
}

