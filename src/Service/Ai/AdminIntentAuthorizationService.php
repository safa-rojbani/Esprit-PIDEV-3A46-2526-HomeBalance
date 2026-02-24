<?php

namespace App\Service\Ai;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

final class AdminIntentAuthorizationService
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function assertAdmin(User $actor): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new \RuntimeException('Forbidden: admin role required.');
        }

        if ($actor->getId() === null) {
            throw new \RuntimeException('Invalid actor context.');
        }
    }
}

