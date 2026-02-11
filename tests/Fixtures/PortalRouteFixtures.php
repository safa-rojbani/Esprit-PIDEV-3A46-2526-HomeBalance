<?php

namespace App\Tests\Fixtures;

final class PortalRouteFixtures
{
    /**
     * @return array<string, array{path: string, selector: string, contains?: string|null}>
     */
    public static function publicAuthPages(): array
    {
        return [
            'login-page' => [
                'path' => '/portal/auth/login',
                'selector' => '.panel-head h2',
                'contains' => 'Sign in',
            ],
            'register-page' => [
                'path' => '/portal/auth/register',
                'selector' => 'form#formAuthentication',
                'contains' => null,
            ],
            'forgot-password-page' => [
                'path' => '/portal/auth/forgot-password',
                'selector' => 'form#formAuthentication',
                'contains' => null,
            ],
            'resend-verification-page' => [
                'path' => '/portal/auth/resend-verification',
                'selector' => 'form',
                'contains' => null,
            ],
            'reset-password-page' => [
                'path' => '/portal/auth/reset-password',
                'selector' => 'form',
                'contains' => null,
            ],
        ];
    }

    /**
     * @return array<string, array{path: string, redirect: string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'admin-users' => [
                'path' => '/portal/admin/users',
                'redirect' => '/portal/auth/login',
            ],
            'account-settings' => [
                'path' => '/portal/account',
                'redirect' => '/portal/auth/login',
            ],
            'account-notifications' => [
                'path' => '/portal/account/notifications',
                'redirect' => '/portal/auth/login',
            ],
            'account-preferences' => [
                'path' => '/portal/account/preferences',
                'redirect' => '/portal/auth/login',
            ],
        ];
    }
}
