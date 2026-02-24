<?php

namespace App\Repository;

use App\Entity\CategorieAchat;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategorieAchat>
 */
class CategorieAchatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategorieAchat::class);
    }

    //    /**
    //     * @return CategorieAchat[] Returns an array of CategorieAchat objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?CategorieAchat
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * @return array<int, CategorieAchat>
     */
    public function searchByFamily(Family $family, string $query): array
    {
        $term = trim($query);
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.family = :family')
            ->setParameter('family', $family)
            ->orderBy('c.nomCategorie', 'ASC');

        if ($term !== '') {
            $qb->andWhere('LOWER(c.nomCategorie) LIKE :q')
                ->setParameter('q', '%'.strtolower($term).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
