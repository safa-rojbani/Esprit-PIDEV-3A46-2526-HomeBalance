<?php

namespace App\Repository;

use App\Entity\AuditTrail;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditTrail>
 */
class AuditTrailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditTrail::class);
    }

    /**
     * @param list<string> $actions
     */
    public function hasAnyActionSince(User $user, array $actions, DateTimeImmutable $since): bool
    {
        if ($actions === []) {
            return false;
        }

        $count = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.user = :user')
            ->andWhere('a.action IN (:actions)')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('actions', $actions)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param list<string> $actions
     */
    public function latestActionAt(User $user, array $actions): ?DateTimeImmutable
    {
        if ($actions === []) {
            return null;
        }

        $result = $this->createQueryBuilder('a')
            ->select('a.createdAt')
            ->andWhere('a.user = :user')
            ->andWhere('a.action IN (:actions)')
            ->setParameter('user', $user)
            ->setParameter('actions', $actions)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!is_array($result) || !array_key_exists('createdAt', $result)) {
            return null;
        }

        $value = $result['createdAt'];

        return $value instanceof DateTimeImmutable ? $value : null;
    }

    //    /**
    //     * @return AuditTrail[] Returns an array of AuditTrail objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?AuditTrail
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
