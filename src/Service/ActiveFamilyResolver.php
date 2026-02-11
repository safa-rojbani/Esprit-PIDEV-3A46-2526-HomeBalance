<?php

namespace App\Service;

use App\Entity\Family;
use App\Entity\User;
use App\Repository\FamilyMembershipRepository;

final class ActiveFamilyResolver
{
    public function __construct(
        private readonly FamilyMembershipRepository $membershipRepository,
    ) {
    }

    public function resolveForUser(User $user): ?Family
    {
        $membership = $this->membershipRepository->findActiveMembershipForUser($user);
        if ($membership !== null) {
            return $membership->getFamily();
        }

        return $user->getFamily();
    }

    public function hasActiveFamily(User $user): bool
    {
        return $this->resolveForUser($user) !== null;
    }
}
