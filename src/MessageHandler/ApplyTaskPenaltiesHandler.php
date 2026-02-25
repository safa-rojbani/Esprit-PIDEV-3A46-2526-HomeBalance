<?php

namespace App\MessageHandler;

use App\Message\ApplyTaskPenaltiesMessage;
use App\Message\TaskCompleted;
use App\Service\TaskPenaltyService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ApplyTaskPenaltiesHandler
{
    public function __construct(
        private readonly TaskPenaltyService $taskPenaltyService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(ApplyTaskPenaltiesMessage $message): void
    {
        $result = $this->taskPenaltyService->applyMissedPenalties();
        foreach ($result['changedFamilies'] as $familyId) {
            $this->messageBus->dispatch(new TaskCompleted([
                'familyId' => $familyId,
            ]));
        }
    }
}
