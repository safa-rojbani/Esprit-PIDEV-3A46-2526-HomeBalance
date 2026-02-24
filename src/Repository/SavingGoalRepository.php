<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\SavingGoal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SavingGoal>
 */
class SavingGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavingGoal::class);
    }

    /**
     * @return array<int, SavingGoal>
     */
    public function searchByFamily(Family $family, string $query): array
    {
        $term = trim($query);
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.family = :family')
            ->setParameter('family', $family)
            ->orderBy('g.createdAt', 'DESC');

        if ($term !== '') {
            $qb->andWhere('LOWER(g.name) LIKE :q')
                ->setParameter('q', '%'.strtolower($term).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
