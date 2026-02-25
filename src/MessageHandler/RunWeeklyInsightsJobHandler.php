<?php

namespace App\MessageHandler;

use App\Message\RunWeeklyInsightsJobMessage;
use App\Service\Ai\WeeklyInsightsOrchestrator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RunWeeklyInsightsJobHandler
{
    public function __construct(
        private readonly WeeklyInsightsOrchestrator $weeklyInsightsOrchestrator,
    ) {
    }

    public function __invoke(RunWeeklyInsightsJobMessage $message): void
    {
        $this->weeklyInsightsOrchestrator->generateForAllFamilies(null, $message->force);
    }
}
