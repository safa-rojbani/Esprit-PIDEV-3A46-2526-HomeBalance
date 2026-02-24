<?php

namespace App\Repository;

use App\Entity\Achat;
use App\Entity\CategorieAchat;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Achat>
 */
class AchatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Achat::class);
    }

    //    /**
    //     * @return Achat[] Returns an array of Achat objects
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

    //    public function findOneBySomeField($value): ?Achat
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * @return array<int, Achat>
     */
    public function searchByFamily(Family $family, string $query): array
    {
        return $this->createFilteredByFamilyQueryBuilder($family, $query)->getQuery()->getResult();
    }

    public function createFilteredByFamilyQueryBuilder(
        Family $family,
        string $query = '',
        ?CategorieAchat $categorie = null,
        ?string $month = null
    ): QueryBuilder {
        $term = trim($query);
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.categorie', 'c')
            ->addSelect('c')
            ->andWhere('a.family = :family')
            ->setParameter('family', $family)
            ->orderBy('a.createdAt', 'DESC');

        if ($term !== '') {
            $q = '%'.strtolower($term).'%';
            $qb->andWhere('LOWER(a.nomArticle) LIKE :q OR LOWER(COALESCE(c.nomCategorie, \'\')) LIKE :q')
                ->setParameter('q', $q);
        }

        if ($categorie !== null) {
            $qb->andWhere('a.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00');
            if ($start instanceof \DateTimeImmutable) {
                $end = $start->modify('first day of next month');
                $qb->andWhere('a.createdAt >= :startMonth')
                    ->andWhere('a.createdAt < :endMonth')
                    ->setParameter('startMonth', $start)
                    ->setParameter('endMonth', $end);
            }
        }

        return $qb;
    }
}
