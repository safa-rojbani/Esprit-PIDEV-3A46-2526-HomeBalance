<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\FamilyBadgeRepository;
use App\Repository\FamilyMembershipRepository;

final class UserMetricsFormatter
{
    public function __construct(
        private readonly FamilyMembershipRepository $membershipRepository,
        private readonly FamilyBadgeRepository $familyBadgeRepository,
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(User $user): array
    {
        $badges = $user->getBadges();
        $family = $user->getFamily();
        $memberships = $family ? $this->membershipRepository->findActiveMemberships($family) : [];
        $familyBadges = $family ? $this->familyBadgeRepository->findByFamily($family) : [];

        return [
            'badgeCount' => $badges->count(),
            'familyName' => $family?->getName(),
            'familyMembers' => count($memberships),
            'status' => $user->getStatus()?->name,
            'role' => $user->getSystemRole()?->name,
            'badges' => array_map(static fn ($badge) => [
                'name' => $badge->getName(),
                'description' => $badge->getDescription(),
                'icon' => $badge->getIcon(),
                'code' => $badge->getCode(),
            ], $badges->toArray()),
            'familyBadges' => array_map(static function ($familyBadge) {
                $badge = $familyBadge->getBadge();

                return [
                    'name' => $badge?->getName(),
                    'description' => $badge?->getDescription(),
                    'icon' => $badge?->getIcon(),
                    'code' => $badge?->getCode(),
                    'awardedAt' => $familyBadge->getAwardedAt(),
                ];
            }, $familyBadges),
        ];
    }
}
