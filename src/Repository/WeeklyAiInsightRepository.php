<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\WeeklyAiInsight;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeeklyAiInsight>
 */
class WeeklyAiInsightRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeeklyAiInsight::class);
    }

    public function findOneForFamilyAndWeek(Family $family, \DateTimeImmutable $weekStart): ?WeeklyAiInsight
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.family = :family')
            ->andWhere('w.weekStart = :weekStart')
            ->setParameter('family', $family)
            ->setParameter('weekStart', $weekStart)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestForFamily(Family $family): ?WeeklyAiInsight
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.family = :family')
            ->setParameter('family', $family)
            ->orderBy('w.weekStart', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
