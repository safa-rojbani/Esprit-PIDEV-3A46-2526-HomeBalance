<?php

declare(strict_types=1);

namespace App\Tests\Service\Ai;

use App\Service\Ai\AdminIntentCatalog;
use App\Service\Ai\AdminIntentValidatorService;
use PHPUnit\Framework\TestCase;

final class AdminIntentValidatorServiceTest extends TestCase
{
    public function testValidatorRejectsUnknownFields(): void
    {
        $service = new AdminIntentValidatorService();

        $result = $service->validate([
            'intent' => AdminIntentCatalog::INTENT_BULK_SUSPEND_USERS,
            'filters' => [
                'status' => 'ACTIVE',
                'bad_field' => 'x',
            ],
            'limit' => 20,
            'reason' => 'test',
            'unexpected' => 'x',
        ]);

        self::assertFalse($result['valid']);
        self::assertNotEmpty($result['errors']);
    }

    public function testValidatorRequiresExplicitTargetForTargetedIntents(): void
    {
        $service = new AdminIntentValidatorService();

        $result = $service->validate([
            'intent' => AdminIntentCatalog::INTENT_RESET_USER_PASSWORD,
            'filters' => [],
            'limit' => 1,
            'reason' => 'reset target',
        ]);

        self::assertFalse($result['valid']);
        self::assertContains('A specific target is required (user_id or filters.email).', $result['errors']);
    }

    public function testValidatorAcceptsEmailArrayForTargetedIntents(): void
    {
        $service = new AdminIntentValidatorService();

        $result = $service->validate([
            'intent' => AdminIntentCatalog::INTENT_EXPORT_AUDIT_FOR_USER,
            'filters' => [
                'email' => ['john@example.com', ' jane@example.com '],
            ],
            'limit' => 2,
            'reason' => 'export target',
        ]);

        self::assertTrue($result['valid']);
        self::assertSame(['john@example.com', 'jane@example.com'], $result['normalized']['filters']['email']);
    }

    public function testValidatorCapsLimitAndNormalizesStatus(): void
    {
        $service = new AdminIntentValidatorService();

        $result = $service->validate([
            'intent' => AdminIntentCatalog::INTENT_SEARCH_USERS_WITH_RISK_FILTERS,
            'filters' => [
                'status' => 'suspended',
            ],
            'limit' => 500,
            'reason' => 'find suspended',
        ]);

        self::assertTrue($result['valid']);
        self::assertSame(100, $result['normalized']['limit']);
        self::assertSame('SUSPENDED', $result['normalized']['filters']['status']);
    }
}
