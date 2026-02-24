<?php

namespace App\MessageHandler;

use App\Entity\TaskCompletion;
use App\Message\AnalyzeTaskProofImageMessage;
use App\Message\TaskCompleted;
use App\Service\Ai\ImageTaskEvaluationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class AnalyzeTaskProofImageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageTaskEvaluationService $imageTaskEvaluationService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AnalyzeTaskProofImageMessage $message): void
    {
        $completion = $this->entityManager->getRepository(TaskCompletion::class)->find($message->completionId);
        if (!$completion instanceof TaskCompletion) {
            return;
        }

        try {
            $result = $this->imageTaskEvaluationService->process($completion);
            $this->entityManager->persist($completion);
            if ($completion->getAiEvaluation() !== null) {
                $this->entityManager->persist($completion->getAiEvaluation());
            }
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('AI image evaluation failed.', [
                'completionId' => $message->completionId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $pointsAwarded = (int) ($result['pointsAwarded'] ?? 0);
        $familyId = $result['familyId'] ?? null;
        if ($pointsAwarded > 0 && $familyId !== null) {
            $this->messageBus->dispatch(new TaskCompleted([
                'familyId' => $familyId,
            ]));
        }
    }
}

