<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserStatusChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getEmailVerifiedAt() === null) {
            throw new CustomUserMessageAuthenticationException('Please verify your email before signing in.');
        }

        $status = $user->getStatus();
        if ($status === null) {
            return;
        }

        if ($status === UserStatus::DELETED) {
            throw new CustomUserMessageAuthenticationException('This account has been deleted.');
        }

        if ($status === UserStatus::SUSPENDED) {
            throw new CustomUserMessageAuthenticationException('Your account is temporarily suspended. If you believe this is a mistake, please contact an administrator.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No-op for now; reserved for future device verification hooks
    }
}
