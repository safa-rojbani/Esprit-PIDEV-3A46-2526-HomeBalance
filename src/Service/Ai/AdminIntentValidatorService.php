<?php

namespace App\Service\Ai;

final class AdminIntentValidatorService
{
    /**
     * @param array<string, mixed> $intent
     * @return array{valid: bool, errors: list<string>, normalized: array<string, mixed>}
     */
    public function validate(array $intent): array
    {
        $errors = [];
        $allowedTopLevelKeys = ['intent', 'filters', 'limit', 'reason', 'user_id', 'fallback_used'];
        foreach (array_keys($intent) as $key) {
            if (!is_string($key) || !in_array($key, $allowedTopLevelKeys, true)) {
                $errors[] = sprintf('Unknown top-level field: %s', (string) $key);
            }
        }

        $name = (string) ($intent['intent'] ?? '');
        if (!in_array($name, AdminIntentCatalog::allowedIntents(), true)) {
            $errors[] = 'Unsupported intent.';
        }

        $limit = (int) ($intent['limit'] ?? 50);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $filters = $intent['filters'] ?? [];
        if (!is_array($filters)) {
            $filters = [];
        }

        $allowedFilterKeys = [
            'status',
            'failed_logins_last_days',
            'password_changes_last_days',
            'min_password_changes',
            'email',
            'query',
            'last_active_before_days',
            'risk_score_above',
        ];
        $normalizedFilters = [];
        foreach ($filters as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (!in_array($key, $allowedFilterKeys, true)) {
                $errors[] = sprintf('Unknown filter field: %s', $key);

                continue;
            }

            if ($key === 'email') {
                if (is_string($value)) {
                    $normalizedFilters[$key] = $value;
                } elseif (is_array($value)) {
                    $normalizedFilters[$key] = $value;
                } else {
                    $errors[] = 'email filter must be a string or array of strings.';
                }

                continue;
            }

            if ($key === 'query' || $key === 'status') {
                if (is_scalar($value)) {
                    $normalizedFilters[$key] = (string) $value;
                } else {
                    $errors[] = sprintf('%s filter must be a string.', $key);
                }

                continue;
            }

            if (is_scalar($value)) {
                $normalizedFilters[$key] = $value;
            } else {
                $errors[] = sprintf('%s filter must be scalar.', $key);
            }
        }

        if (isset($normalizedFilters['status'])) {
            $status = strtoupper((string) $normalizedFilters['status']);
            if (in_array($status, ['INACTIVE', 'SUSPENDED'], true)) {
                $normalizedFilters['status'] = 'SUSPENDED';
            } elseif ($status === 'ALL') {
                $normalizedFilters['status'] = 'ALL';
            } else {
                $normalizedFilters['status'] = 'ACTIVE';
            }
        }

        foreach (['failed_logins_last_days', 'password_changes_last_days', 'min_password_changes', 'last_active_before_days', 'risk_score_above'] as $numericKey) {
            if (isset($normalizedFilters[$numericKey])) {
                $normalizedFilters[$numericKey] = max(0, (int) $normalizedFilters[$numericKey]);
            }
        }

        if (isset($normalizedFilters['email'])) {
            $emailValue = $normalizedFilters['email'];
            if (is_string($emailValue)) {
                $normalizedFilters['email'] = trim($emailValue);
            } elseif (is_array($emailValue)) {
                $normalizedFilters['email'] = array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $emailValue), static fn (string $item): bool => $item !== ''));
            }
        }

        $normalized = [
            'intent' => $name,
            'filters' => $normalizedFilters,
            'limit' => $limit,
            'reason' => trim((string) ($intent['reason'] ?? '')),
            'user_id' => isset($intent['user_id']) ? (string) $intent['user_id'] : null,
            'fallback_used' => (bool) ($intent['fallback_used'] ?? false),
        ];

        if (in_array($name, [
            AdminIntentCatalog::INTENT_RESET_USER_PASSWORD,
            AdminIntentCatalog::INTENT_EXPORT_AUDIT_FOR_USER,
        ], true)) {
            $hasUserId = (($normalized['user_id'] ?? '') !== '');
            $emailFilter = $normalizedFilters['email'] ?? null;
            $hasEmailTarget = false;
            if (is_string($emailFilter)) {
                $hasEmailTarget = trim($emailFilter) !== '';
            } elseif (is_array($emailFilter)) {
                $hasEmailTarget = $emailFilter !== [];
            }

            if (!$hasUserId && !$hasEmailTarget) {
                $errors[] = 'A specific target is required (user_id or filters.email).';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'normalized' => $normalized,
        ];
    }

    /**
     * @param list<string> $errors
     */
    public function buildUserFriendlyMessage(array $errors): string
    {
        if ($errors === []) {
            return 'Your request is valid.';
        }

        if (in_array('Unsupported intent.', $errors, true)) {
            return 'I could not identify a valid action. Please mention one of: suspend, reactivate, reset password, search risk users, export audit.';
        }

        if (count(array_filter($errors, static fn (string $error): bool => str_contains($error, 'Unknown filter field'))) > 0) {
            return 'Some filters are not supported. Try using: status, failed logins days, password changes days, email, inactivity days, or risk score.';
        }

        if (in_array('A specific target is required (user_id or filters.email).', $errors, true)) {
            return 'This action needs a specific target user. Please provide an email or explicit target.';
        }

        return 'Your request is ambiguous. Please rephrase with a clear action and target.';
    }
}
