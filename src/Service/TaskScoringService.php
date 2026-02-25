<?php

namespace App\Service;

use App\Entity\Score;
use App\Entity\ScoreHistory;
use App\Entity\TaskCompletion;
use App\Repository\ScoreHistoryRepository;
use App\Repository\ScoreRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TaskScoringService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ScoreRepository $scoreRepository,
        private readonly ScoreHistoryRepository $scoreHistoryRepository,
        private readonly TaskPointResolver $taskPointResolver,
    ) {
    }

    public function awardPointsForValidatedCompletion(TaskCompletion $completion, bool $awardedByAi = false): int
    {
        if ($completion->isValidated() !== true) {
            return 0;
        }

        $task = $completion->getTask();
        $user = $completion->getUser();
        $family = $task?->getFamily();
        if ($task === null || $user === null || $family === null) {
            return 0;
        }

        $score = $this->scoreRepository->findOneForUserAndFamily($user, $family);
        if ($score === null) {
            $score = new Score();
            $score->setUser($user);
            $score->setFamily($family);
            $score->setTotalPoints(0);
            $score->setLastUpdated(new \DateTimeImmutable());
            $this->entityManager->persist($score);
        }

        if ($this->scoreHistoryRepository->hasAwardForCompletion($completion)) {
            return 0;
        }

        if ($this->scoreHistoryRepository->hasAwardForScoreTask($score, $task) && $completion->getId() === null) {
            return 0;
        }

        $points = $this->taskPointResolver->resolvePoints($task, $completion->getValidatedAt());
        if ($points <= 0) {
            return 0;
        }

        $history = new ScoreHistory();
        $history->setScore($score);
        $history->setTask($task);
        $history->setCompletion($completion);
        $history->setAwardedByAi($awardedByAi);
        $history->setPoints($points);
        $history->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($history);

        $score->setTotalPoints(($score->getTotalPoints() ?? 0) + $points);
        $score->setLastUpdated(new \DateTimeImmutable());

        return $points;
    }
}
