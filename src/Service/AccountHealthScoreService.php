<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\AuditTrailRepository;
use App\Repository\FamilyMembershipRepository;
use DateInterval;
use DateTimeImmutable;

final class AccountHealthScoreService
{
    public function __construct(
        private readonly AuditTrailRepository $auditTrailRepository,
        private readonly FamilyMembershipRepository $membershipRepository,
    ) {
    }

    /**
     * @return array{score: int, label: string, explanations: list<string>}
     */
    public function evaluate(User $user): array
    {
        $now = new DateTimeImmutable();
        $score = 0;
        $explanations = [];

        if ($user->getEmailVerifiedAt() !== null) {
            $score += 20;
            $explanations[] = 'Email verified (+20)';
        } else {
            $explanations[] = 'Email not verified (+0)';
        }

        if ($this->membershipRepository->findActiveMembershipForUser($user) !== null) {
            $score += 15;
            $explanations[] = 'Active family membership (+15)';
        } else {
            $explanations[] = 'No active family membership (+0)';
        }

        if ($this->isProfileComplete($user)) {
            $score += 10;
            $explanations[] = 'Profile is complete (+10)';
        } else {
            $explanations[] = 'Profile still incomplete (+0)';
        }

        $lastLogin = $user->getLastLogin();
        $recentLoginSince = $now->sub(new DateInterval('P7D'));
        if ($lastLogin !== null && $lastLogin >= $recentLoginSince) {
            $score += 15;
            $explanations[] = 'Recent login activity (+15)';
        } else {
            $explanations[] = 'No recent login in the last 7 days (+0)';
        }

        $failedLoginSince = $now->sub(new DateInterval('P30D'));
        $hasRecentFailedLogin = $this->auditTrailRepository->hasAnyActionSince(
            $user,
            ['user.login.failed', 'auth.login.failed'],
            $failedLoginSince,
        );
        if (!$hasRecentFailedLogin) {
            $score += 15;
            $explanations[] = 'No failed login in the last 30 days (+15)';
        } else {
            $explanations[] = 'Failed login detected in the last 30 days (+0)';
        }

        $lastPasswordUpdate = $this->auditTrailRepository->latestActionAt(
            $user,
            ['user.password.changed', 'user.reset.completed'],
        );
        $recentPasswordSince = $now->sub(new DateInterval('P180D'));
        if ($lastPasswordUpdate !== null && $lastPasswordUpdate >= $recentPasswordSince) {
            $score += 10;
            $explanations[] = 'Password updated in the last 180 days (+10)';
        } else {
            $explanations[] = 'Password update older than 180 days (+0)';
        }

        if ($this->hasConfiguredNotifications($user)) {
            $score += 5;
            $explanations[] = 'Notifications configured (+5)';
        } else {
            $explanations[] = 'Notifications not configured (+0)';
        }

        if ($user->getStatus() === UserStatus::SUSPENDED) {
            $score -= 20;
            $explanations[] = 'Account suspended (-20)';
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'label' => $this->labelFor($score),
            'explanations' => $explanations,
        ];
    }

    private function isProfileComplete(User $user): bool
    {
        if ($user->getFirstName() === null || trim($user->getFirstName()) === '') {
            return false;
        }

        if ($user->getLastName() === null || trim($user->getLastName()) === '') {
            return false;
        }

        if ($user->getEmail() === null || trim($user->getEmail()) === '') {
            return false;
        }

        if ($user->getBirthDate() === null) {
            return false;
        }

        if ($user->getLocale() === null || trim($user->getLocale()) === '') {
            return false;
        }

        if ($user->getTimeZone() === null || trim($user->getTimeZone()) === '') {
            return false;
        }

        return true;
    }

    private function hasConfiguredNotifications(User $user): bool
    {
        $preferences = $user->getPreferences();
        if (!is_array($preferences)) {
            return false;
        }

        $matrix = $preferences['notifications']['matrix'] ?? null;
        if (!is_array($matrix)) {
            return false;
        }

        foreach ($matrix as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $enabled) {
                if ($enabled === true) {
                    return true;
                }
            }
        }

        return false;
    }

    private function labelFor(int $score): string
    {
        if ($score >= 80) {
            return 'Excellent';
        }

        if ($score >= 55) {
            return 'Good';
        }

        return 'Needs Attention';
    }
}
