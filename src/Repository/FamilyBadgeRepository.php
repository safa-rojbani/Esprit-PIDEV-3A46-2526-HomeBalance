<?php

namespace App\Repository;

use App\Entity\Badge;
use App\Entity\Family;
use App\Entity\FamilyBadge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FamilyBadge>
 */
class FamilyBadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FamilyBadge::class);
    }

    /**
     * @return FamilyBadge[]
     */
    public function findByFamily(Family $family): array
    {
        return $this->createQueryBuilder('fb')
            ->addSelect('b')
            ->join('fb.badge', 'b')
            ->andWhere('fb.family = :family')
            ->setParameter('family', $family)
            ->orderBy('fb.awardedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByFamilyAndBadge(Family $family, Badge $badge): ?FamilyBadge
    {
        return $this->createQueryBuilder('fb')
            ->andWhere('fb.family = :family')
            ->andWhere('fb.badge = :badge')
            ->setParameter('family', $family)
            ->setParameter('badge', $badge)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return FamilyBadge[]
     */
    public function findByBadge(Badge $badge): array
    {
        return $this->createQueryBuilder('fb')
            ->addSelect('f')
            ->join('fb.family', 'f')
            ->andWhere('fb.badge = :badge')
            ->setParameter('badge', $badge)
            ->getQuery()
            ->getResult();
    }

    public function countByBadge(Badge $badge): int
    {
        return (int) $this->createQueryBuilder('fb')
            ->select('COUNT(fb.id)')
            ->andWhere('fb.badge = :badge')
            ->setParameter('badge', $badge)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
