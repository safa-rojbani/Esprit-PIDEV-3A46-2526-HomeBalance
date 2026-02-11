<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class EmailVerificationService
{
    private const TOKEN_LENGTH = 32;
    private const TTL = 'PT48H';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function sendVerification(User $user): void
    {
        if ($user->getEmailVerifiedAt() !== null) {
            return;
        }

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));

        $user->setEmailVerificationToken($token);
        $user->setEmailVerificationRequestedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        $this->notificationService->sendAccountNotification($user, 'verify_email', [
            'token' => $token,
        ]);
    }

    public function resendForEmail(string $identifier): void
    {
        $identifier = trim(mb_strtolower($identifier));
        if ($identifier === '') {
            return;
        }

        $user = $this->userRepository->loadUserByIdentifier($identifier);
        if (!$user || $user->getEmailVerifiedAt() !== null) {
            return;
        }

        $this->sendVerification($user);
    }

    public function verifyToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $token]);
        if (!$user) {
            return false;
        }

        $requestedAt = $user->getEmailVerificationRequestedAt();
        if ($requestedAt === null) {
            return false;
        }

        $expiry = $requestedAt->add(new DateInterval(self::TTL));
        if ($expiry < new DateTimeImmutable()) {
            return false;
        }

        $user->setEmailVerifiedAt(new DateTimeImmutable());
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationRequestedAt(null);

        $this->entityManager->flush();

        return true;
    }
}
