<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\PointRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PointRule>
 */
class PointRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PointRule::class);
    }

    public function findActiveForTask(Task $task, \DateTimeImmutable $at): ?PointRule
    {
        return $this->createQueryBuilder('pr')
            ->andWhere('pr.task = :task')
            ->andWhere('(pr.validFrom IS NULL OR pr.validFrom <= :at)')
            ->andWhere('(pr.validTo IS NULL OR pr.validTo >= :at)')
            ->setParameter('task', $task)
            ->setParameter('at', $at)
            ->orderBy('pr.validFrom', 'DESC')
            ->addOrderBy('pr.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param array<int, Task> $tasks
     * @return array<int, PointRule>
     */
    public function findActiveForTasks(array $tasks, \DateTimeImmutable $at): array
    {
        if ($tasks === []) {
            return [];
        }

        $rules = $this->createQueryBuilder('pr')
            ->andWhere('pr.task IN (:tasks)')
            ->andWhere('(pr.validFrom IS NULL OR pr.validFrom <= :at)')
            ->andWhere('(pr.validTo IS NULL OR pr.validTo >= :at)')
            ->setParameter('tasks', $tasks)
            ->setParameter('at', $at)
            ->getQuery()
            ->getResult();

        $byTask = [];
        foreach ($rules as $rule) {
            $taskId = $rule->getTask()?->getId();
            if ($taskId === null) {
                continue;
            }

            $existing = $byTask[$taskId] ?? null;
            if ($existing === null || $this->isRuleHigherPriority($rule, $existing)) {
                $byTask[$taskId] = $rule;
            }
        }

        return $byTask;
    }

    public function findLatestForTask(Task $task): ?PointRule
    {
        return $this->createQueryBuilder('pr')
            ->andWhere('pr.task = :task')
            ->setParameter('task', $task)
            ->orderBy('pr.validFrom', 'DESC')
            ->addOrderBy('pr.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function isRuleHigherPriority(PointRule $candidate, PointRule $current): bool
    {
        $candidateFrom = $candidate->getValidFrom()?->getTimestamp() ?? PHP_INT_MIN;
        $currentFrom = $current->getValidFrom()?->getTimestamp() ?? PHP_INT_MIN;

        if ($candidateFrom !== $currentFrom) {
            return $candidateFrom > $currentFrom;
        }

        return ($candidate->getId() ?? 0) > ($current->getId() ?? 0);
    }

    //    /**
    //     * @return PointRule[] Returns an array of PointRule objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?PointRule
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
