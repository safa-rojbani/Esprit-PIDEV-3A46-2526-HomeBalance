<?php

namespace App\Service;

use App\Entity\AccountNotification;
use App\Entity\User;
use App\Message\SendAccountNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendAccountNotification(User $user, string $key, array $context = []): void
    {
        $notification = $this->createNotification($user, $key, 'email', 'PENDING', $context);
        $this->messageBus->dispatch(new SendAccountNotification($notification->getId()));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendInAppNotification(User $user, string $key, array $context = []): void
    {
        $this->createNotification($user, $key, 'app', 'SENT', $context, new \DateTimeImmutable());
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createNotification(
        User $user,
        string $key,
        string $channel,
        string $status,
        array $context,
        ?\DateTimeImmutable $sentAt = null
    ): AccountNotification {
        $notification = (new AccountNotification())
            ->setUser($user)
            ->setKey($key)
            ->setChannel($channel)
            ->setStatus($status)
            ->setPayload($context)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setSentAt($sentAt);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}
