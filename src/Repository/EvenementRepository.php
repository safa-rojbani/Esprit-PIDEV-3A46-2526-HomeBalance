<?php

namespace App\Repository;

use App\Entity\Evenement;
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
            return $this->createQueryBuilder('e')
                ->andWhere('e.createdBy IS NULL')
                ->orderBy('e.dateDebut', 'ASC')
                ->getQuery()
                ->getResult();
        }
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.createdBy = :user OR e.createdBy IS NULL')
            ->setParameter('user', $user)
            ->orderBy('e.dateDebut', 'ASC');

        $family = $user->getFamily();
        if ($family !== null) {
            $qb->orWhere('e.shareWithFamily = true AND e.family = :family')
                ->setParameter('family', $family);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findForUserWithFilters(User $user, ?TypeEvenement $type, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC');

        $visibility = $qb->expr()->eq('e.createdBy', ':user');
        $qb->setParameter('user', $user);

        $family = $user->getFamily();
        if ($family !== null) {
            $visibility = $qb->expr()->orX(
                $visibility,
                $qb->expr()->andX(
                    $qb->expr()->eq('e.shareWithFamily', 'true'),
                    $qb->expr()->eq('e.family', ':family')
                )
            );
            $qb->setParameter('family', $family);
        }

        $qb->andWhere($visibility);

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
    public function findWithFilters(?TypeEvenement $type, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
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
