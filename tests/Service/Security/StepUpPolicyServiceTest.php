<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use App\Service\Security\StepUpPolicyService;
use PHPUnit\Framework\TestCase;

final class StepUpPolicyServiceTest extends TestCase
{
    public function testProtectedActionRequiresStepUpWhenFeatureEnabled(): void
    {
        $service = new StepUpPolicyService(true);

        self::assertTrue($service->isStepUpRequired('admin.user.reset_password'));
        self::assertTrue($service->isStepUpRequired('admin.ai.execute'));
        self::assertFalse($service->isStepUpRequired('admin.user.view'));
    }

    public function testFeatureFlagDisablesStepUpRequirement(): void
    {
        $service = new StepUpPolicyService(false);

        self::assertFalse($service->isStepUpRequired('admin.user.reset_password'));
        self::assertFalse($service->isStepUpRequired('admin.ai.execute'));
    }
}

