<?php

namespace App\Repository;

use App\Entity\Revenu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Revenu>
 */
class RevenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Revenu::class);
    }

    //    /**
    //     * @return Revenu[] Returns an array of Revenu objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Revenu
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function sumAll(): string
{
    return (string) $this->createQueryBuilder('r')
        ->select('COALESCE(SUM(r.montant), 0)')
        ->getQuery()
        ->getSingleScalarResult();
}

public function findDistinctTypesAll(): array
{
    $rows = $this->createQueryBuilder('r')
        ->select('DISTINCT r.typeRevenu AS type')
        ->andWhere('r.typeRevenu IS NOT NULL')
        ->orderBy('r.typeRevenu', 'ASC')
        ->getQuery()
        ->getArrayResult();

    return array_values(array_filter(array_map(fn($x) => $x['type'] ?? null, $rows)));
}

}
