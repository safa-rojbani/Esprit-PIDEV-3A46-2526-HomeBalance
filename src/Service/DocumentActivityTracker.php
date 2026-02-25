<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentActivityLog;
use App\Entity\Family;
use App\Entity\User;
use App\Enum\DocumentActivityEvent;
use Doctrine\ORM\EntityManagerInterface;

final class DocumentActivityTracker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param array<string, scalar|array|null> $metadata
     */
    public function track(
        Family $family,
        ?User $user,
        ?Document $document,
        DocumentActivityEvent $event,
        ?string $channel = null,
        array $metadata = [],
        ?\DateTimeImmutable $createdAt = null
    ): void {
        $log = (new DocumentActivityLog())
            ->setFamily($family)
            ->setUser($user)
            ->setDocument($document)
            ->setEventType($event->value)
            ->setChannel($channel)
            ->setMetadata($metadata !== [] ? $metadata : null)
            ->setCreatedAt($createdAt ?? new \DateTimeImmutable());

        $this->entityManager->persist($log);
    }
}

