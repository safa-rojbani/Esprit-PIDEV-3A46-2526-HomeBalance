<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\Score;
use App\Entity\ScoreHistory;
use App\Entity\Task;
use App\Entity\TaskCompletion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScoreHistory>
 */
class ScoreHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScoreHistory::class);
    }

    public function hasAwardForScoreTask(Score $score, Task $task): bool
    {
        if ($score->getId() === null) {
            return false;
        }

        $count = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.score = :score')
            ->andWhere('h.task = :task')
            ->setParameter('score', $score)
            ->setParameter('task', $task)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function hasAwardForCompletion(TaskCompletion $completion): bool
    {
        if ($completion->getId() === null) {
            return false;
        }

        $count = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.completion = :completion')
            ->setParameter('completion', $completion)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * @return ScoreHistory[]
     */
    public function findForFamilyWithFilters(
        Family $family,
        string $search,
        ?User $member,
        string $sort,
        int $limit = 50,
        string $source = 'all'
    ): array {
        $qb = $this->createQueryBuilder('h')
            ->addSelect('s', 'u', 't', 'tc')
            ->join('h.score', 's')
            ->join('s.user', 'u')
            ->join('h.task', 't')
            ->leftJoin('h.completion', 'tc')
            ->andWhere('s.family = :family')
            ->setParameter('family', $family);

        if ($search !== '') {
            $qb
                ->andWhere('LOWER(t.title) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        if ($member instanceof User) {
            $qb
                ->andWhere('s.user = :member')
                ->setParameter('member', $member);
        }

        if ($source === 'ai') {
            $qb->andWhere('h.awardedByAi = true');
        } elseif ($source === 'manual') {
            $qb->andWhere('h.awardedByAi = false');
        }

        switch ($sort) {
            case 'oldest':
                $qb->orderBy('h.createdAt', 'ASC');
                break;
            case 'points_desc':
                $qb->orderBy('h.points', 'DESC')->addOrderBy('h.createdAt', 'DESC');
                break;
            case 'points_asc':
                $qb->orderBy('h.points', 'ASC')->addOrderBy('h.createdAt', 'DESC');
                break;
            default:
                $qb->orderBy('h.createdAt', 'DESC');
                break;
        }

        return $qb
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return ScoreHistory[] Returns an array of ScoreHistory objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ScoreHistory
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
