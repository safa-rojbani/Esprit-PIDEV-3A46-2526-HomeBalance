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

}
