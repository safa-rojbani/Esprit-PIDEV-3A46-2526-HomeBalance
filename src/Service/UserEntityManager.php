<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use DateTimeImmutable;
use InvalidArgumentException;

final class UserEntityManager
{
    /**
     * Validate User business rules.
     *
     * Rules:
     * 1) createdAt must not be in the future.
     * 2) If resetToken exists, resetExpiresAt must be provided and in the future.
     */
    public function validate(User $user): void
    {
        $createdAt = $user->getCreatedAt();
        if ($createdAt === null) {
            throw new InvalidArgumentException('User createdAt is required.');
        }

        if ($createdAt > new DateTimeImmutable()) {
            throw new InvalidArgumentException('User createdAt cannot be in the future.');
        }

        $resetToken = trim((string) $user->getResetToken());
        if ($resetToken !== '') {
            $resetExpiresAt = $user->getResetExpiresAt();
            if ($resetExpiresAt === null) {
                throw new InvalidArgumentException('resetExpiresAt is required when resetToken is set.');
            }

            if ($resetExpiresAt <= new DateTimeImmutable()) {
                throw new InvalidArgumentException('resetExpiresAt must be in the future when resetToken is set.');
            }
        }
    }
}
