<?php

namespace App\Service\Ai;

final class AdminIntentCatalog
{
    public const INTENT_BULK_SUSPEND_USERS = 'bulk_suspend_users';
    public const INTENT_BULK_REACTIVATE_USERS = 'bulk_reactivate_users';
    public const INTENT_RESET_USER_PASSWORD = 'reset_user_password';
    public const INTENT_SEARCH_USERS_WITH_RISK_FILTERS = 'search_users_with_risk_filters';
    public const INTENT_EXPORT_AUDIT_FOR_USER = 'export_audit_for_user';

    /**
     * @return list<string>
     */
    public static function allowedIntents(): array
    {
        return [
            self::INTENT_BULK_SUSPEND_USERS,
            self::INTENT_BULK_REACTIVATE_USERS,
            self::INTENT_RESET_USER_PASSWORD,
            self::INTENT_SEARCH_USERS_WITH_RISK_FILTERS,
            self::INTENT_EXPORT_AUDIT_FOR_USER,
        ];
    }

    public static function isDangerous(string $intent): bool
    {
        return in_array($intent, [
            self::INTENT_BULK_SUSPEND_USERS,
            self::INTENT_BULK_REACTIVATE_USERS,
            self::INTENT_RESET_USER_PASSWORD,
        ], true);
    }

    public static function maxImpact(string $intent): int
    {
        return match ($intent) {
            self::INTENT_BULK_SUSPEND_USERS,
            self::INTENT_BULK_REACTIVATE_USERS => 50,
            self::INTENT_RESET_USER_PASSWORD,
            self::INTENT_EXPORT_AUDIT_FOR_USER => 1,
            self::INTENT_SEARCH_USERS_WITH_RISK_FILTERS => 100,
            default => 0,
        };
    }
}
