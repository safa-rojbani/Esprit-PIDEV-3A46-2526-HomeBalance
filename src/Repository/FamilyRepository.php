<?php

namespace App\Repository;

use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Family>
 */
class FamilyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Family::class);
    }

    public function findOneActiveByJoinCode(string $joinCode): ?Family
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('f')
            ->andWhere('LOWER(f.joinCode) = LOWER(:code)')
            ->andWhere('f.codeExpiresAt IS NULL OR f.codeExpiresAt > :now')
            ->setParameter('code', $joinCode)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return Family[] Returns an array of Family objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Family
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
