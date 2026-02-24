<?php

namespace App\Repository;

use App\Entity\HistoriqueAchat;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoriqueAchat>
 */
class HistoriqueAchatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueAchat::class);
    }

    //    /**
    //     * @return HistoriqueAchat[] Returns an array of HistoriqueAchat objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('h.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?HistoriqueAchat
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
public function sumAll(): string
{
    return (string) $this->createQueryBuilder('h')
        ->select('COALESCE(SUM(h.montantAchete), 0)')
        ->getQuery()
        ->getSingleScalarResult();
}

public function sumByFamily(Family $family): string
{
    return (string) $this->createQueryBuilder('h')
        ->select('COALESCE(SUM(h.montantAchete), 0)')
        ->andWhere('h.family = :family')
        ->setParameter('family', $family)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * @return array<int, HistoriqueAchat>
 */
public function searchByFamily(Family $family, string $query): array
{
    $term = trim($query);

    $qb = $this->createQueryBuilder('h')
        ->leftJoin('h.achat', 'a')
        ->leftJoin('h.paidBy', 'p')
        ->andWhere('h.family = :family')
        ->setParameter('family', $family)
        ->orderBy('h.dateAchat', 'DESC');

    if ($term !== '') {
        $q = '%'.strtolower($term).'%';
        $qb->andWhere('LOWER(a.nomArticle) LIKE :q OR LOWER(CONCAT(p.firstName, \' \', p.lastName)) LIKE :q')
            ->setParameter('q', $q);
    }

    return $qb->getQuery()->getResult();
}

public function averageMonthlyByFamily(Family $family, int $months = 3): string
{
    $months = max(1, $months);
    $fromDate = (new \DateTimeImmutable('first day of this month'))->modify(sprintf('-%d months', $months - 1));

    $sum = (string) $this->createQueryBuilder('h')
        ->select('COALESCE(SUM(h.montantAchete), 0)')
        ->andWhere('h.family = :family')
        ->andWhere('h.dateAchat >= :fromDate')
        ->setParameter('family', $family)
        ->setParameter('fromDate', $fromDate)
        ->getQuery()
        ->getSingleScalarResult();

    if (function_exists('bcdiv')) {
        return bcdiv($sum, (string) $months, 2);
    }

    return number_format(((float) $sum) / $months, 2, '.', '');
}

public function sumByFamilyAndPeriod(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): string
{
    return (string) $this->createQueryBuilder('h')
        ->select('COALESCE(SUM(h.montantAchete), 0)')
        ->andWhere('h.family = :family')
        ->andWhere('h.dateAchat >= :fromDate')
        ->andWhere('h.dateAchat < :toDate')
        ->setParameter('family', $family)
        ->setParameter('fromDate', $from)
        ->setParameter('toDate', $to)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * @return array<int, array{category: string, total: string}>
 */
public function categoryTotalsByFamilyAndPeriod(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
{
    $rows = $this->createQueryBuilder('h')
        ->select("COALESCE(c.nomCategorie, 'Sans categorie') AS category")
        ->addSelect('COALESCE(SUM(h.montantAchete), 0) AS total')
        ->leftJoin('h.achat', 'a')
        ->leftJoin('a.categorie', 'c')
        ->andWhere('h.family = :family')
        ->andWhere('h.dateAchat >= :fromDate')
        ->andWhere('h.dateAchat < :toDate')
        ->setParameter('family', $family)
        ->setParameter('fromDate', $from)
        ->setParameter('toDate', $to)
        ->groupBy('c.id, c.nomCategorie')
        ->orderBy('total', 'DESC')
        ->getQuery()
        ->getArrayResult();

    return array_map(static fn(array $row): array => [
        'category' => (string) ($row['category'] ?? 'Sans categorie'),
        'total' => (string) ($row['total'] ?? '0'),
    ], $rows);
}

}
