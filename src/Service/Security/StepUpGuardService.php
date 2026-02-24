<?php

namespace App\Service\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

final class StepUpGuardService
{
    public function __construct(
        private readonly BiometricVerificationService $biometricVerificationService,
        private readonly StepUpPolicyService $stepUpPolicyService,
    ) {
    }

    public function needsVerification(string $actionKey): bool
    {
        return $this->stepUpPolicyService->isStepUpRequired($actionKey);
    }

    public function isSatisfied(Request $request, string $actionKey, ?User $targetUser = null): bool
    {
        if (!$this->needsVerification($actionKey)) {
            return true;
        }

        return $this->biometricVerificationService->isVerifiedForAction($request, $actionKey, $targetUser);
    }
}

