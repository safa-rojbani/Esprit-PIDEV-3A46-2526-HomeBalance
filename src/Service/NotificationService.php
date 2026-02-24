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
        $notification = (new AccountNotification())
            ->setUser($user)
            ->setKey($key)
            ->setChannel('email')
            ->setStatus('PENDING')
            ->setPayload($context)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new SendAccountNotification($notification->getId()));
    }
}
