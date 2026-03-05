<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Task;
use App\Enum\TaskDifficulty;
use App\Enum\TaskRecurrence;
use App\Service\TaskManager;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TaskManagerTest extends TestCase
{
    #[Test]
    public function validateAcceptsValidTask(): void
    {
        $task = (new Task())
            ->setTitle('Faire les devoirs')
            ->setDescription('Terminer les exercices de mathematiques')
            ->setRecurrence(TaskRecurrence::WEEKLY)
            ->setDifficulty(TaskDifficulty::MEDIUM)
            ->setCreatedAt(new DateTimeImmutable('-1 day'))
            ->setIsActive(true);

        $manager = new TaskManager();

        $manager->validate($task);

        self::assertTrue(true);
    }

    #[Test]
    public function validateThrowsWhenCreatedAtIsInFuture(): void
    {
        $task = (new Task())
            ->setTitle('Nettoyage')
            ->setDescription('Nettoyer la chambre')
            ->setRecurrence(TaskRecurrence::WEEKLY)
            ->setDifficulty(TaskDifficulty::EASY)
            ->setCreatedAt(new DateTimeImmutable('+1 day'))
            ->setIsActive(true);

        $manager = new TaskManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Task createdAt cannot be in the future.');

        $manager->validate($task);
    }

    #[Test]
    public function validateThrowsWhenDailyTaskIsHard(): void
    {
        $task = (new Task())
            ->setTitle('Routine du matin')
            ->setDescription('Routine complete avant l ecole')
            ->setRecurrence(TaskRecurrence::DAILY)
            ->setDifficulty(TaskDifficulty::HARD)
            ->setCreatedAt(new DateTimeImmutable('-2 hours'))
            ->setIsActive(true);

        $manager = new TaskManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A daily task cannot have hard difficulty.');

        $manager->validate($task);
    }
}
