<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\FamilyInvitation;
use App\Enum\InvitationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FamilyInvitation>
 */
class FamilyInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FamilyInvitation::class);
    }

    public function findActiveByJoinCode(string $code): ?FamilyInvitation
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('fi')
            ->addSelect('f')
            ->join('fi.family', 'f')
            ->andWhere('LOWER(fi.joinCode) = LOWER(:code)')
            ->andWhere('fi.status = :status')
            ->andWhere('fi.expiresAt IS NULL OR fi.expiresAt > :now')
            ->setParameter('code', $code)
            ->setParameter('status', InvitationStatus::PENDING->value)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return FamilyInvitation[]
     */
    public function findRecentByFamily(Family $family, int $limit = 10): array
    {
        return $this->createQueryBuilder('fi')
            ->andWhere('fi.family = :family')
            ->setParameter('family', $family)
            ->orderBy('fi.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return FamilyInvitation[] Returns an array of FamilyInvitation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?FamilyInvitation
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
