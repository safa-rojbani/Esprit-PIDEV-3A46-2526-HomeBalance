<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetService
{
    private const TOKEN_LENGTH = 32;
    private const TTL = 'PT1H';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly NotificationService $notificationService,
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    public function requestReset(string $email): void
    {
        $identifier = trim(mb_strtolower($email));
        if ($identifier === '') {
            return;
        }

        $user = $this->userRepository->loadUserByIdentifier($identifier);
        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval(self::TTL));

        $user->setResetToken($token);
        $user->setResetExpiresAt($expiresAt);

        $this->entityManager->flush();

        $this->notificationService->sendAccountNotification($user, 'reset_requested', [
            'token' => $token,
            'expiresAt' => $expiresAt->format(DateTimeImmutable::ATOM),
        ]);
        $this->auditTrailService->record($user, 'user.reset.requested');
    }

    public function resetPassword(string $token, string $plainPassword): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $user = $this->userRepository->findOneBy(['resetToken' => $token]);
        if (!$user) {
            return false;
        }

        $expiresAt = $user->getResetExpiresAt();
        if ($expiresAt === null || $expiresAt < new DateTimeImmutable()) {
            return false;
        }

        $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);
        $user->setResetToken(null);
        $user->setResetExpiresAt(null);
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        $this->notificationService->sendAccountNotification($user, 'password_reset');
        $this->auditTrailService->record($user, 'user.reset.completed');

        return true;
    }
}
