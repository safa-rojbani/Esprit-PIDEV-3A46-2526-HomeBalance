<?php

namespace App\MessageHandler\SMS;

use App\Message\SMS\RecalculateActivityPatternMessage;
use App\Repository\UserRepository;
use App\Service\SMS\ActivityPatternService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RecalculateActivityPatternHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ActivityPatternService $activityPatternService,
    ) {
    }

    public function __invoke(RecalculateActivityPatternMessage $message): void
    {
        $user = $this->userRepository->find($message->getUserId());
        
        if (!$user) {
            return;
        }

        // Calculate and persist peak hours
        $this->activityPatternService->calculatePeakHours($user);
    }
}
