<?php

namespace App\Repository;

use App\Entity\Revenu;
use App\Entity\Family;
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

public function sumByFamily(Family $family): string
{
    return (string) $this->createQueryBuilder('r')
        ->select('COALESCE(SUM(r.montant), 0)')
        ->andWhere('r.family = :family')
        ->setParameter('family', $family)
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

public function findDistinctTypesByFamily(Family $family): array
{
    $rows = $this->createQueryBuilder('r')
        ->select('DISTINCT r.typeRevenu AS type')
        ->andWhere('r.typeRevenu IS NOT NULL')
        ->andWhere('r.family = :family')
        ->setParameter('family', $family)
        ->orderBy('r.typeRevenu', 'ASC')
        ->getQuery()
        ->getArrayResult();

    return array_values(array_filter(array_map(static fn(array $row) => $row['type'] ?? null, $rows)));
}

/**
 * @return array<int, Revenu>
 */
public function searchByFamily(Family $family, string $query): array
{
    $term = trim($query);
    if ($term == '') {
        return $this->findBy(['family' => $family], ['dateRevenu' => 'DESC']);
    }

    return $this->createQueryBuilder('r')
        ->andWhere('r.family = :family')
        ->andWhere('LOWER(r.typeRevenu) LIKE :q')
        ->setParameter('family', $family)
        ->setParameter('q', '%'.strtolower($term).'%')
        ->orderBy('r.dateRevenu', 'DESC')
        ->getQuery()
        ->getResult();
}

public function averageMonthlyByFamily(Family $family, int $months = 3): string
{
    $months = max(1, $months);
    $fromDate = (new \DateTimeImmutable('first day of this month'))->modify(sprintf('-%d months', $months - 1));

    $sum = (string) $this->createQueryBuilder('r')
        ->select('COALESCE(SUM(r.montant), 0)')
        ->andWhere('r.family = :family')
        ->andWhere('r.dateRevenu >= :fromDate')
        ->setParameter('family', $family)
        ->setParameter('fromDate', $fromDate)
        ->getQuery()
        ->getSingleScalarResult();

    if (function_exists('bcdiv')) {
        return bcdiv($sum, (string) $months, 2);
    }

    return number_format(((float) $sum) / $months, 2, '.', '');
}

}
