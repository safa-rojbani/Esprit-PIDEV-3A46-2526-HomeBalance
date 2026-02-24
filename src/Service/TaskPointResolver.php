<?php

namespace App\Service;

use App\Entity\Task;
use App\Enum\TaskDifficulty;
use App\Repository\PointRuleRepository;

final class TaskPointResolver
{
    public function __construct(
        private readonly PointRuleRepository $pointRuleRepository,
    ) {
    }

    public function resolvePoints(Task $task, ?\DateTimeImmutable $at = null): int
    {
        $at ??= new \DateTimeImmutable();
        $rule = $this->pointRuleRepository->findActiveForTask($task, $at);
        if ($rule !== null && $rule->getPoints() !== null) {
            return max(0, $rule->getPoints());
        }

        return $this->resolveDefaultPointsFromDifficulty($task);
    }

    /**
     * @param array<int, Task> $tasks
     * @return array<int, int>
     */
    public function resolvePointsForTasks(array $tasks, ?\DateTimeImmutable $at = null): array
    {
        $at ??= new \DateTimeImmutable();
        $rulesByTaskId = $this->pointRuleRepository->findActiveForTasks($tasks, $at);
        $pointsByTaskId = [];

        foreach ($tasks as $task) {
            $taskId = $task->getId();
            if ($taskId === null) {
                continue;
            }

            $rule = $rulesByTaskId[$taskId] ?? null;
            if ($rule !== null && $rule->getPoints() !== null) {
                $pointsByTaskId[$taskId] = max(0, $rule->getPoints());
                continue;
            }

            $pointsByTaskId[$taskId] = $this->resolveDefaultPointsFromDifficulty($task);
        }

        return $pointsByTaskId;
    }

    private function resolveDefaultPointsFromDifficulty(Task $task): int
    {
        return match ($task->getDifficulty()) {
            TaskDifficulty::EASY => 10,
            TaskDifficulty::MEDIUM => 20,
            TaskDifficulty::HARD => 35,
            default => 10,
        };
    }
}

