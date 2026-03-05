<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Enum\TaskDifficulty;
use App\Enum\TaskRecurrence;
use DateTimeImmutable;
use InvalidArgumentException;

final class TaskManager
{
    /**
     * Validate Task business rules.
     *
     * Rules:
     * 1) createdAt must not be in the future.
     * 2) A daily task cannot be hard.
     */
    public function validate(Task $task): void
    {
        $createdAt = $task->getCreatedAt();
        if ($createdAt === null) {
            throw new InvalidArgumentException('Task createdAt is required.');
        }

        if ($createdAt > new DateTimeImmutable()) {
            throw new InvalidArgumentException('Task createdAt cannot be in the future.');
        }

        $recurrence = $task->getRecurrence();
        if ($recurrence === null) {
            throw new InvalidArgumentException('Task recurrence is required.');
        }

        $difficulty = $task->getDifficulty();
        if ($difficulty === null) {
            throw new InvalidArgumentException('Task difficulty is required.');
        }

        if ($recurrence === TaskRecurrence::DAILY && $difficulty === TaskDifficulty::HARD) {
            throw new InvalidArgumentException('A daily task cannot have hard difficulty.');
        }
    }
}
