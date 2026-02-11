<?php

namespace App\MessageHandler;

use App\Entity\Family;
use App\Message\TaskCompleted;
use App\Service\BadgeAwardingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TaskCompletedHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BadgeAwardingService $badgeAwardingService,
    ) {
    }

    public function __invoke(TaskCompleted $message): void
    {
        $familyId = $message->payload['familyId'] ?? null;
        if ($familyId === null) {
            return;
        }

        $family = $this->entityManager->getRepository(Family::class)->find($familyId);
        if (!$family instanceof Family) {
            return;
        }

        $this->badgeAwardingService->awardForFamilyIfReady($family);
    }
}
