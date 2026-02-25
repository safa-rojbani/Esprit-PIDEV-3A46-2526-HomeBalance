<?php

namespace App\Service;

use App\Entity\Score;
use App\Entity\ScoreHistory;
use App\Entity\TaskAssignment;
use App\Entity\TaskCompletion;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\TaskAssignmentStatus;
use App\Repository\ScoreRepository;
use App\Repository\TaskAssignmentRepository;
use App\Repository\TaskCompletionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TaskPenaltyService
{
    private const LATE_RATIO = 0.30;
    private const MISSED_RATIO = 0.60;
    private const LATE_MIN = 2;
    private const MISSED_MIN = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ScoreRepository $scoreRepository,
        private readonly TaskAssignmentRepository $taskAssignmentRepository,
        private readonly TaskCompletionRepository $taskCompletionRepository,
        private readonly TaskPointResolver $taskPointResolver,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function applyLatePenalty(TaskAssignment $assignment, TaskCompletion $completion): int
    {
        $dueDate = $assignment->getDueDate();
        $completedAt = $completion->getCompletedAt();

        if ($dueDate === null || $completedAt === null || $completedAt <= $dueDate) {
            return 0;
        }

        if ($assignment->getPenaltyAppliedAt() !== null) {
            return 0;
        }

        $penalty = $this->computePenaltyPoints($assignment, self::LATE_RATIO, self::LATE_MIN);

        return $this->applyPenalty($assignment, $penalty, 'late');
    }

    /**
     * @return array{checked: int, penalized: int, changedFamilies: array<int, int>}
     */
    public function applyMissedPenalties(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $assignments = $this->taskAssignmentRepository->findOverdueWithoutPenalty($now);

        $checked = 0;
        $penalized = 0;
        $changedFamilies = [];

        foreach ($assignments as $assignment) {
            ++$checked;

            $task = $assignment->getTask();
            $user = $assignment->getUser();
            $dueDate = $assignment->getDueDate();
            if ($task === null || $user === null || $dueDate === null) {
                continue;
            }

            $latestCompletion = $this->taskCompletionRepository->findLatestForTaskAndUser($task, $user);

            if ($latestCompletion instanceof TaskCompletion) {
                $completedAt = $latestCompletion->getCompletedAt();
                if ($completedAt !== null && $completedAt <= $dueDate) {
                    if ($assignment->getStatus() !== TaskAssignmentStatus::COMPLETED) {
                        $assignment->setStatus(TaskAssignmentStatus::COMPLETED);
                    }
                    continue;
                }

                $deducted = $this->applyLatePenalty($assignment, $latestCompletion);
                if ($deducted !== 0) {
                    ++$penalized;
                    $familyId = $assignment->getFamily()?->getId();
                    if ($familyId !== null) {
                        $changedFamilies[$familyId] = $familyId;
                    }
                }
                continue;
            }

            $missedPenalty = $this->computePenaltyPoints($assignment, self::MISSED_RATIO, self::MISSED_MIN);
            $deducted = $this->applyPenalty($assignment, $missedPenalty, 'missed');
            if ($deducted === 0) {
                continue;
            }

            ++$penalized;
            $familyId = $assignment->getFamily()?->getId();
            if ($familyId !== null) {
                $changedFamilies[$familyId] = $familyId;
            }
        }

        $this->entityManager->flush();

        return [
            'checked' => $checked,
            'penalized' => $penalized,
            'changedFamilies' => array_values($changedFamilies),
        ];
    }

    private function computePenaltyPoints(TaskAssignment $assignment, float $ratio, int $minimum): int
    {
        $task = $assignment->getTask();
        if ($task === null) {
            return $minimum;
        }

        $basePoints = $this->taskPointResolver->resolvePoints($task, $assignment->getDueDate());
        $scaled = (int) round($basePoints * $ratio);

        return max($minimum, $scaled);
    }

    private function applyPenalty(TaskAssignment $assignment, int $penaltyPoints, string $reason): int
    {
        if ($penaltyPoints <= 0) {
            return 0;
        }

        $task = $assignment->getTask();
        $user = $assignment->getUser();
        $family = $assignment->getFamily();
        if ($task === null || $user === null || $family === null) {
            return 0;
        }

        if ($assignment->getPenaltyAppliedAt() !== null) {
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

        $deduction = -abs($penaltyPoints);
        $history = new ScoreHistory();
        $history->setScore($score);
        $history->setTask($task);
        $history->setCompletion(null);
        $history->setAwardedByAi(false);
        $history->setPoints($deduction);
        $history->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($history);

        $assignment->setPenaltyAppliedAt(new \DateTimeImmutable());
        $assignment->setPenaltyPoints($deduction);

        if ($reason === 'missed' && $assignment->getStatus() !== TaskAssignmentStatus::CANCELLED) {
            $assignment->setStatus(TaskAssignmentStatus::CANCELLED);
        }

        $score->setTotalPoints(($score->getTotalPoints() ?? 0) + $deduction);
        $score->setLastUpdated(new \DateTimeImmutable());

        $this->sendPenaltyNotifications($assignment, $deduction, $reason);

        return $deduction;
    }

    private function sendPenaltyNotifications(TaskAssignment $assignment, int $deduction, string $reason): void
    {
        $task = $assignment->getTask();
        $member = $assignment->getUser();
        $family = $assignment->getFamily();
        $dueDate = $assignment->getDueDate();
        if ($task === null || $member === null || $family === null) {
            return;
        }

        $memberName = trim(((string) $member->getFirstName()) . ' ' . ((string) $member->getLastName()));
        if ($memberName === '') {
            $memberName = (string) $member->getEmail();
        }

        $payload = [
            'taskTitle' => (string) $task->getTitle(),
            'familyName' => (string) $family->getName(),
            'dueDate' => $dueDate?->format('d/m/Y H:i'),
            'points' => $deduction,
            'reason' => $reason,
            'reasonLabel' => $reason === 'missed' ? 'Tache non faite' : 'Retard',
            'memberName' => $memberName,
        ];

        $this->notificationService->sendAccountNotification($member, 'task_penalty_applied', $payload);
        $this->notificationService->sendInAppNotification($member, 'task_penalty_applied', $payload);

        $members = $this->userRepository->findFamilyMembers($family);
        foreach ($members as $familyMember) {
            if ($familyMember->getId() === $member->getId()) {
                continue;
            }

            if ($familyMember->getFamilyRole() !== FamilyRole::PARENT) {
                continue;
            }

            $this->notificationService->sendAccountNotification($familyMember, 'task_penalty_parent_alert', $payload);
            $this->notificationService->sendInAppNotification($familyMember, 'task_penalty_parent_alert', $payload);
        }
    }
}
