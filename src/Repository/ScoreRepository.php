<?php

namespace App\Repository;

use App\Entity\Score;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Score>
 */
class ScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Score::class);
    }

    /**
     * @return Score[]
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('u', 'f')
            ->join('s.user', 'u')
            ->join('s.family', 'f')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Score[]
     */
    public function findAllWithRelationsForFamily(\App\Entity\Family $family): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('u', 'f')
            ->join('s.user', 'u')
            ->join('s.family', 'f')
            ->andWhere('s.family = :family')
            ->setParameter('family', $family)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Score[] Returns an array of Score objects
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

    //    public function findOneBySomeField($value): ?Score
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
