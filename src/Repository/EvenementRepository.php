<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\Family;
use App\Entity\TypeEvenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * @return Evenement[]
     */
    public function findVisibleForUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $family = $user->getFamily();

        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC');
        if ($family === null) {
            $qb->andWhere('e.family IS NULL');
        } else {
            $qb->andWhere('(e.family = :family OR e.family IS NULL)')
                ->setParameter('family', $family);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findForFamilyWithFilters(Family $family, ?TypeEvenement $type, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC');

        $qb->andWhere('(e.family = :family OR e.family IS NULL)')
            ->setParameter('family', $family);

        if ($type !== null) {
            $qb->andWhere('e.TypeEvenement = :type')
                ->setParameter('type', $type);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('e.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findWithFilters(Family $family, ?TypeEvenement $type, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC');

        $qb->andWhere('e.family = :family')
            ->setParameter('family', $family);

        if ($type !== null) {
            $qb->andWhere('e.TypeEvenement = :type')
                ->setParameter('type', $type);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('e.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findAdminDefaultsWithFilters(User $admin, ?TypeEvenement $type, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.family IS NULL')
            ->andWhere('e.createdBy = :admin')
            ->setParameter('admin', $admin)
            ->orderBy('e.dateDebut', 'ASC');

        if ($type !== null) {
            $qb->andWhere('e.TypeEvenement = :type')
                ->setParameter('type', $type);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('e.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Evenement[] Returns an array of Evenement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Evenement
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
