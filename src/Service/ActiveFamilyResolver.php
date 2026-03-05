<?php

namespace App\Service;

use App\Entity\Family;
use App\Entity\User;
use App\Repository\FamilyMembershipRepository;

final class ActiveFamilyResolver
{
    /**
     * Request-local cache to avoid repeating the same membership query
     * several times during one HTTP request lifecycle.
     *
     * @var array<string, Family|null>
     */
    private array $resolvedFamilies = [];

    public function __construct(
        private readonly FamilyMembershipRepository $membershipRepository,
    ) {
    }

    public function resolveForUser(User $user): ?Family
    {
        $cacheKey = $this->buildCacheKey($user);
        if (array_key_exists($cacheKey, $this->resolvedFamilies)) {
            return $this->resolvedFamilies[$cacheKey];
        }

        $membership = $this->membershipRepository->findActiveMembershipForUser($user);
        if ($membership !== null) {
            return $this->resolvedFamilies[$cacheKey] = $membership->getFamily();
        }

        return $this->resolvedFamilies[$cacheKey] = $user->getFamily();
    }

    public function hasActiveFamily(User $user): bool
    {
        return $this->resolveForUser($user) !== null;
    }

    private function buildCacheKey(User $user): string
    {
        $id = $user->getId();
        if (is_string($id) && $id !== '') {
            return 'id:' . $id;
        }

        return 'obj:' . spl_object_id($user);
    }
}
