<?php

namespace App\Service;

use App\Entity\Family;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\FamilyMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PortalNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FamilyMembershipRepository $membershipRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function notifyFamily(Family $family, User $actor, string $type, array $payload = []): void
    {
        $memberships = $this->membershipRepository->findActiveMemberships($family);
        $recipients = [];

        foreach ($memberships as $membership) {
            $recipient = $membership->getUser();
            $recipientId = $recipient->getId();
            if ($recipientId === null || isset($recipients[$recipientId])) {
                continue;
            }

            $recipients[$recipientId] = $recipient;
        }

        if ($recipients === []) {
            $creator = $family->getCreatedBy();
            if ($creator !== null && $creator->getId() !== null) {
                $recipients[$creator->getId()] = $creator;
            }
        }

        $now = new \DateTimeImmutable();
        foreach ($recipients as $recipient) {
            $notification = (new Notification())
                ->setFamily($family)
                ->setRecipient($recipient)
                ->setActor($actor)
                ->setType($type)
                ->setPayload($payload)
                ->setIsRead(false)
                ->setCreatedAt($now);

            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();
    }
}
