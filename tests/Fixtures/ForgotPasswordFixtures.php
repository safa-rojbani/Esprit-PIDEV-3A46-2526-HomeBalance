<?php

namespace App\Tests\Fixtures;

final class ForgotPasswordFixtures
{
    /**
     * @return array<string, array{email: string}>
     */
    public static function invalidEmails(): array
    {
        return [
            'empty-input' => ['email' => ''],
            'missing-at-symbol' => ['email' => 'invalid.example.com'],
            'missing-domain' => ['email' => 'user@'],
        ];
    }

    /**
     * @return array<string, array{email: string}>
     */
    public static function validEmails(): array
    {
        return [
            'unknown-user' => ['email' => 'unknown.user@example.com'],
            'uppercase-domain' => ['email' => 'person@EXAMPLE.ORG'],
        ];
    }
}
