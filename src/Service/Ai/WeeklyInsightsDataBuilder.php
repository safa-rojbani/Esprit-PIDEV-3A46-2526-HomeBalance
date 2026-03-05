<?php

namespace App\Service\Ai;

use App\Entity\Family;
use App\Entity\ScoreHistory;
use App\Entity\TaskAssignment;
use App\Entity\TaskCompletion;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\TaskAssignmentStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class WeeklyInsightsDataBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Family $family, \DateTimeImmutable $weekStart, ?\DateTimeImmutable $weekEnd = null): array
    {
        $weekStart = $weekStart->setTime(0, 0, 0);
        $weekEnd ??= $weekStart->modify('+7 days');
        $prevWeekStart = $weekStart->modify('-7 days');
        $prevWeekEnd = $weekStart;

        $members = $this->userRepository->findFamilyMembers($family);
        $memberStats = $this->initializeMemberStats($members);

        $currentEntries = $this->findScoreEntries($family, $weekStart, $weekEnd);
        $previousEntries = $this->findScoreEntries($family, $prevWeekStart, $prevWeekEnd);
        $currentCompletions = $this->findCompletions($family, $weekStart, $weekEnd);
        $previousCompletions = $this->findCompletions($family, $prevWeekStart, $prevWeekEnd);
        $currentDueAssignments = $this->findAssignmentsDue($family, $weekStart, $weekEnd);

        $taskIssueMap = [];

        foreach ($currentEntries as $entry) {
            if (!$entry instanceof ScoreHistory) {
                continue;
            }

            $user = $entry->getScore()?->getUser();
            $userId = $user?->getId();
            if ($userId === null || !isset($memberStats[$userId])) {
                continue;
            }

            $points = (int) ($entry->getPoints() ?? 0);
            $memberStats[$userId]['pointsCurrent'] += $points;
            if ($points >= 0) {
                ++$memberStats[$userId]['positiveEventsCurrent'];
            } else {
                ++$memberStats[$userId]['negativeEventsCurrent'];
                $task = $entry->getTask();
                if ($task !== null && $task->getId() !== null) {
                    $taskId = $task->getId();
                    $taskIssueMap[$taskId] ??= $this->newTaskIssueBucket((string) $task->getTitle());
                    ++$taskIssueMap[$taskId]['penalties'];
                    $taskIssueMap[$taskId]['deducted'] += abs($points);
                }
            }
        }

        foreach ($previousEntries as $entry) {
            if (!$entry instanceof ScoreHistory) {
                continue;
            }

            $userId = $entry->getScore()?->getUser()?->getId();
            if ($userId === null || !isset($memberStats[$userId])) {
                continue;
            }
            $memberStats[$userId]['pointsPrevious'] += (int) ($entry->getPoints() ?? 0);
        }

        foreach ($currentCompletions as $completion) {
            if (!$completion instanceof TaskCompletion) {
                continue;
            }

            $user = $completion->getUser();
            $userId = $user?->getId();
            if ($userId === null || !isset($memberStats[$userId])) {
                continue;
            }

            if ($completion->isValidated() === true) {
                ++$memberStats[$userId]['validatedCurrent'];
            } elseif ($completion->isValidated() === false) {
                ++$memberStats[$userId]['refusedCurrent'];
                $task = $completion->getTask();
                if ($task !== null && $task->getId() !== null) {
                    $taskId = $task->getId();
                    $taskIssueMap[$taskId] ??= $this->newTaskIssueBucket((string) $task->getTitle());
                    ++$taskIssueMap[$taskId]['refusals'];
                }
            } else {
                ++$memberStats[$userId]['pendingCurrent'];
            }
        }

        foreach ($previousCompletions as $completion) {
            if (!$completion instanceof TaskCompletion || $completion->isValidated() !== true) {
                continue;
            }

            $userId = $completion->getUser()?->getId();
            if ($userId === null || !isset($memberStats[$userId])) {
                continue;
            }
            ++$memberStats[$userId]['validatedPrevious'];
        }

        foreach ($currentDueAssignments as $assignment) {
            if (!$assignment instanceof TaskAssignment) {
                continue;
            }

            $user = $assignment->getUser();
            $task = $assignment->getTask();
            $userId = $user?->getId();
            if ($userId === null || !isset($memberStats[$userId])) {
                continue;
            }

            ++$memberStats[$userId]['assignmentsDueCurrent'];

            if ($assignment->getPenaltyAppliedAt() !== null && ($assignment->getPenaltyPoints() ?? 0) < 0) {
                $taskId = $task?->getId();
                if ($taskId !== null) {
                    $taskIssueMap[$taskId] ??= $this->newTaskIssueBucket((string) $task->getTitle());
                }

                if ($assignment->getStatus() === TaskAssignmentStatus::COMPLETED) {
                    ++$memberStats[$userId]['lateCurrent'];
                    if ($taskId !== null) {
                        ++$taskIssueMap[$taskId]['late'];
                    }
                } elseif ($assignment->getStatus() === TaskAssignmentStatus::CANCELLED && $taskId !== null) {
                    ++$taskIssueMap[$taskId]['missed'];
                }

                continue;
            }

            $dueDate = $assignment->getDueDate();
            $status = $assignment->getStatus();
            if (
                $dueDate !== null
                && $dueDate < new \DateTimeImmutable()
                && ($status === TaskAssignmentStatus::ASSIGNED || $status === TaskAssignmentStatus::ACCEPTED)
                && $task !== null
                && $task->getId() !== null
            ) {
                $taskId = $task->getId();
                $taskIssueMap[$taskId] ??= $this->newTaskIssueBucket((string) $task->getTitle());
                ++$taskIssueMap[$taskId]['overdueOpen'];
            }
        }

        foreach ($memberStats as $memberId => $stats) {
            $delta = $stats['pointsCurrent'] - $stats['pointsPrevious'];
            $memberStats[$memberId]['deltaPoints'] = $delta;
        }

        $mostImproved = $this->resolveMostImproved($memberStats);
        $familyTotals = $this->buildFamilyTotals($memberStats);
        $blockingTasks = $this->buildBlockingTasks($taskIssueMap);
        $engagementSeed = $this->buildEngagementSeed($memberStats, $blockingTasks);

        return [
            'period' => [
                'start' => $weekStart->format('Y-m-d'),
                'end' => $weekEnd->format('Y-m-d'),
            ],
            'family' => [
                'id' => $family->getId(),
                'name' => (string) $family->getName(),
            ],
            'familyTotals' => $familyTotals,
            'mostImprovedCandidate' => $mostImproved,
            'memberStats' => array_values($memberStats),
            'blockingTasks' => $blockingTasks,
            'engagementSeed' => $engagementSeed,
        ];
    }

    /**
     * @param array<int, User> $members
     * @return array<string, array<string, mixed>>
     */
    private function initializeMemberStats(array $members): array
    {
        $stats = [];
        foreach ($members as $member) {
            $memberId = $member->getId();
            if ($memberId === null) {
                continue;
            }

            $stats[$memberId] = [
                'memberId' => $memberId,
                'memberName' => $this->displayName($member),
                'role' => $member->getFamilyRole()->value,
                'pointsCurrent' => 0,
                'pointsPrevious' => 0,
                'deltaPoints' => 0,
                'positiveEventsCurrent' => 0,
                'negativeEventsCurrent' => 0,
                'validatedCurrent' => 0,
                'validatedPrevious' => 0,
                'pendingCurrent' => 0,
                'refusedCurrent' => 0,
                'assignmentsDueCurrent' => 0,
                'lateCurrent' => 0,
            ];
        }

        return $stats;
    }

    /**
     * @return array<int, ScoreHistory>
     */
    private function findScoreEntries(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('h', 's', 'u', 't')
            ->from(ScoreHistory::class, 'h')
            ->join('h.score', 's')
            ->join('s.user', 'u')
            ->join('h.task', 't')
            ->where('s.family = :family')
            ->andWhere('h.createdAt >= :from')
            ->andWhere('h.createdAt < :to')
            ->setParameter('family', $family)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, TaskCompletion>
     */
    private function findCompletions(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('tc', 't', 'u')
            ->from(TaskCompletion::class, 'tc')
            ->join('tc.task', 't')
            ->join('tc.user', 'u')
            ->where('t.family = :family')
            ->andWhere('tc.completedAt >= :from')
            ->andWhere('tc.completedAt < :to')
            ->setParameter('family', $family)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, TaskAssignment>
     */
    private function findAssignmentsDue(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a', 't', 'u')
            ->from(TaskAssignment::class, 'a')
            ->join('a.task', 't')
            ->join('a.user', 'u')
            ->where('a.family = :family')
            ->andWhere('a.dueDate IS NOT NULL')
            ->andWhere('a.dueDate >= :from')
            ->andWhere('a.dueDate < :to')
            ->setParameter('family', $family)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, array<string, mixed>> $memberStats
     * @return array<string, mixed>
     */
    private function resolveMostImproved(array $memberStats): array
    {
        $rows = array_values($memberStats);
        usort($rows, static fn (array $a, array $b): int => (int) $b['deltaPoints'] <=> (int) $a['deltaPoints']);

        $winner = $rows[0] ?? null;
        if (!is_array($winner)) {
            return [
                'memberId' => null,
                'memberName' => 'Aucun membre',
                'deltaPoints' => 0,
                'insightSeed' => 'Pas assez de donnees cette semaine.',
            ];
        }

        $delta = (int) $winner['deltaPoints'];

        return [
            'memberId' => (string) $winner['memberId'],
            'memberName' => (string) $winner['memberName'],
            'deltaPoints' => $delta,
            'insightSeed' => $delta > 0
                ? sprintf('%s progresse de %+d pts par rapport a la semaine precedente.', $winner['memberName'], $delta)
                : sprintf('%s reste stable cette semaine (%+d pts).', $winner['memberName'], $delta),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $memberStats
     * @return array<string, int>
     */
    private function buildFamilyTotals(array $memberStats): array
    {
        $totals = [
            'pointsCurrent' => 0,
            'pointsPrevious' => 0,
            'validatedCurrent' => 0,
            'refusedCurrent' => 0,
            'lateCurrent' => 0,
        ];

        foreach ($memberStats as $row) {
            $totals['pointsCurrent'] += (int) $row['pointsCurrent'];
            $totals['pointsPrevious'] += (int) $row['pointsPrevious'];
            $totals['validatedCurrent'] += (int) $row['validatedCurrent'];
            $totals['refusedCurrent'] += (int) $row['refusedCurrent'];
            $totals['lateCurrent'] += (int) $row['lateCurrent'];
        }

        return $totals;
    }

    /**
     * @param array<int, array<string, int|string>> $taskIssueMap
     * @return array<int, array<string, mixed>>
     */
    private function buildBlockingTasks(array $taskIssueMap): array
    {
        $rows = [];
        foreach ($taskIssueMap as $taskId => $bucket) {
            $severityScore =
                ((int) $bucket['penalties'] * 3)
                + ((int) $bucket['missed'] * 3)
                + ((int) $bucket['refusals'] * 2)
                + ((int) $bucket['late'] * 2)
                + ((int) $bucket['overdueOpen'] * 2)
                + (int) floor(((int) $bucket['deducted']) / 5);

            if ($severityScore <= 0) {
                continue;
            }

            $severity = 'low';
            if ($severityScore >= 9) {
                $severity = 'high';
            } elseif ($severityScore >= 5) {
                $severity = 'medium';
            }

            $rows[] = [
                'taskId' => $taskId,
                'taskTitle' => (string) $bucket['title'],
                'severity' => $severity,
                'severityScore' => $severityScore,
                'metrics' => [
                    'penalties' => (int) $bucket['penalties'],
                    'deducted' => (int) $bucket['deducted'],
                    'refusals' => (int) $bucket['refusals'],
                    'late' => (int) $bucket['late'],
                    'missed' => (int) $bucket['missed'],
                    'overdueOpen' => (int) $bucket['overdueOpen'],
                ],
                'reasonSeed' => $this->buildTaskReasonSeed($bucket),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => (int) $b['severityScore'] <=> (int) $a['severityScore']);

        return array_slice($rows, 0, 5);
    }

    /**
     * @param array<string, mixed> $bucket
     */
    private function buildTaskReasonSeed(array $bucket): string
    {
        $parts = [];
        if ((int) ($bucket['penalties'] ?? 0) > 0) {
            $parts[] = sprintf('%d penalites', (int) $bucket['penalties']);
        }
        if ((int) ($bucket['refusals'] ?? 0) > 0) {
            $parts[] = sprintf('%d refus', (int) $bucket['refusals']);
        }
        if ((int) ($bucket['late'] ?? 0) > 0) {
            $parts[] = sprintf('%d retards', (int) $bucket['late']);
        }
        if ((int) ($bucket['missed'] ?? 0) > 0) {
            $parts[] = sprintf('%d non faites', (int) $bucket['missed']);
        }

        return $parts === [] ? 'Blocage detecte sans detail.' : implode(', ', $parts).'.';
    }

    /**
     * @param array<string, array<string, mixed>> $memberStats
     * @param array<int, array<string, mixed>> $blockingTasks
     * @return array<int, array<string, mixed>>
     */
    private function buildEngagementSeed(array $memberStats, array $blockingTasks): array
    {
        $topBlockingTitle = $blockingTasks[0]['taskTitle'] ?? null;
        $rows = [];

        foreach ($memberStats as $stats) {
            if (($stats['role'] ?? null) !== FamilyRole::CHILD->value) {
                continue;
            }

            $assignmentsDue = max(1, (int) $stats['assignmentsDueCurrent']);
            $completionRate = ((int) $stats['validatedCurrent']) / $assignmentsDue;
            $penaltyLoad = (int) $stats['negativeEventsCurrent'] + (int) $stats['lateCurrent'] + (int) $stats['refusedCurrent'];
            $trend = (int) $stats['deltaPoints'];

            $rawScore = 60 + ($trend * 1.2) + ($completionRate * 25) - ($penaltyLoad * 6);
            $engagementScore = (int) max(0, min(100, round($rawScore)));

            $status = 'stable';
            if ($engagementScore < 45) {
                $status = 'low';
            } elseif ($engagementScore < 70) {
                $status = 'watch';
            }

            $challenges = [];
            if ($completionRate < 0.6) {
                $challenges[] = 'Completer 3 taches avant deadline cette semaine.';
            }
            if ($penaltyLoad > 0) {
                $challenges[] = 'Objectif zero retard pendant 4 jours consecutifs.';
            }
            if ($trend < 0) {
                $challenges[] = 'Recuperer +15 points avec 2 taches faciles.';
            }
            if ($topBlockingTitle !== null) {
                $challenges[] = sprintf('Debloquer la tache "%s" avec aide parentale.', $topBlockingTitle);
            }
            if ($challenges === []) {
                $challenges[] = 'Maintenir la regularite: 1 tache/jour pendant 5 jours.';
            }

            $rows[] = [
                'memberId' => (string) $stats['memberId'],
                'memberName' => (string) $stats['memberName'],
                'engagementScore' => $engagementScore,
                'status' => $status,
                'signalSeed' => sprintf(
                    'Tendance %+d pts, taux de completion %d%%, penalites %d.',
                    $trend,
                    (int) round($completionRate * 100),
                    $penaltyLoad
                ),
                'challenges' => array_values(array_unique(array_slice($challenges, 0, 3))),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, int|string>
     */
    private function newTaskIssueBucket(string $title): array
    {
        return [
            'title' => $title,
            'penalties' => 0,
            'deducted' => 0,
            'refusals' => 0,
            'late' => 0,
            'missed' => 0,
            'overdueOpen' => 0,
        ];
    }

    private function displayName(User $user): string
    {
        $name = trim(((string) $user->getFirstName()) . ' ' . ((string) $user->getLastName()));

        return $name !== '' ? $name : (string) $user->getEmail();
    }
}
