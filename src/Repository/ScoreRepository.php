<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\Score;
use App\Entity\User;
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

    public function findOneForUserAndFamily(User $user, Family $family): ?Score
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.family = :family')
            ->setParameter('user', $user)
            ->setParameter('family', $family)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<string, Score>
     */
    public function findIndexedByUserForFamily(Family $family): array
    {
        $scores = $this->createQueryBuilder('s')
            ->addSelect('u')
            ->join('s.user', 'u')
            ->andWhere('s.family = :family')
            ->setParameter('family', $family)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($scores as $score) {
            $userId = $score->getUser()?->getId();
            if ($userId === null) {
                continue;
            }

            $indexed[$userId] = $score;
        }

        return $indexed;
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
