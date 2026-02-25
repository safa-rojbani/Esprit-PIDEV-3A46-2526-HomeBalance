<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskAssignment;
use App\Entity\User;
use App\Enum\TaskAssignmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskAssignment>
 */
class TaskAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskAssignment::class);
    }

    public function findLatestForTaskAndUser(Task $task, User $user): ?TaskAssignment
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.task = :task')
            ->andWhere('a.user = :user')
            ->setParameter('task', $task)
            ->setParameter('user', $user)
            ->orderBy('a.assignedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<int, TaskAssignment>
     */
    public function findOverdueWithoutPenalty(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('t', 'u', 'f')
            ->join('a.task', 't')
            ->join('a.user', 'u')
            ->join('a.family', 'f')
            ->andWhere('a.dueDate IS NOT NULL')
            ->andWhere('a.dueDate < :now')
            ->andWhere('a.penaltyAppliedAt IS NULL')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('now', $now)
            ->setParameter('statuses', [
                TaskAssignmentStatus::ASSIGNED->value,
                TaskAssignmentStatus::ACCEPTED->value,
            ])
            ->orderBy('a.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return TaskAssignment[] Returns an array of TaskAssignment objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TaskAssignment
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
