<?php

namespace App\Repository;

use App\Entity\DefaultGallery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DefaultGallery>
 */
class DefaultGalleryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DefaultGallery::class);
    }

    /**
     * @return DefaultGallery[]
     */
    public function findForAdminList(string $search, string $sort, string $dir): array
    {
        $sortField = $sort === 'description' ? 'd.description' : 'd.name';
        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('d');

        if ($search !== '') {
            $qb
                ->andWhere('LOWER(d.name) LIKE :search OR LOWER(COALESCE(d.description, \'\')) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        return $qb
            ->orderBy($sortField, $direction)
            ->addOrderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return DefaultGallery[] Returns an array of DefaultGallery objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?DefaultGallery
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
