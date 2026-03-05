<?php

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Family;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function findGlobalAdminTasks(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.family IS NULL')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Task[]
     */
    public function findFamilyTasks(Family $family): array
    {
        return $this->createFamilyTasksQueryBuilder($family)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Task[]
     */
    public function findActiveGlobalAdminTasks(): array
    {
        return $this->createActiveGlobalAdminTasksQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    public function createFamilyTasksQueryBuilder(Family $family): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.family = :family')
            ->setParameter('family', $family)
            ->orderBy('t.createdAt', 'DESC');
    }

    public function createActiveGlobalAdminTasksQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.family IS NULL')
            ->andWhere('t.isActive = true')
            ->orderBy('t.createdAt', 'DESC');
    }

    public function findFamilyDuplicateFromTemplate(Task $template, Family $family): ?Task
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.family = :family')
            ->andWhere('t.title = :title')
            ->andWhere('t.description = :description')
            ->andWhere('t.difficulty = :difficulty')
            ->andWhere('t.recurrence = :recurrence')
            ->setParameter('family', $family)
            ->setParameter('title', $template->getTitle())
            ->setParameter('description', $template->getDescription())
            ->setParameter('difficulty', $template->getDifficulty()?->value)
            ->setParameter('recurrence', $template->getRecurrence()?->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Task[]
     */
    public function findActiveFamilyTasksFiltered(Family $family, string $search, string $sort): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.family = :family')
            ->andWhere('t.isActive = true')
            ->setParameter('family', $family);

        if ($search !== '') {
            $qb
                ->andWhere('LOWER(t.title) LIKE :search OR LOWER(t.description) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        switch ($sort) {
            case 'oldest':
                $qb->orderBy('t.createdAt', 'ASC');
                break;
            case 'title_az':
                $qb->orderBy('t.title', 'ASC');
                break;
            case 'title_za':
                $qb->orderBy('t.title', 'DESC');
                break;
            default:
                $qb->orderBy('t.createdAt', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }


//    /**
//     * @return Task[] Returns an array of Task objects
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

//    public function findOneBySomeField($value): ?Task
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
