<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\UserStatus;
use DateTimeImmutable;

final class UserAnonymizer
{
    public function anonymize(User $user): void
    {
        $suffix = $user->getId() ?? bin2hex(random_bytes(8));
        $placeholderEmail = sprintf('deleted+%s@homebalance.invalid', $suffix);
        $placeholderUsername = sprintf('deleted_%s', str_replace('-', '', (string) $suffix));

        $user
            ->setEmail($placeholderEmail)
            ->setUsername(substr($placeholderUsername, 0, 40))
            ->setFirstName('Deleted')
            ->setLastName('User')
            ->setAvatarPath(null)
            ->setPreferences(null)
            ->setEmailVerificationToken(null)
            ->setEmailVerificationRequestedAt(null)
            ->setEmailVerifiedAt(null)
            ->setResetToken(null)
            ->setResetExpiresAt(null)
            ->setFamily(null)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setStatus(UserStatus::DELETED)
            ->setUpdatedAt(new DateTimeImmutable());
    }
}
