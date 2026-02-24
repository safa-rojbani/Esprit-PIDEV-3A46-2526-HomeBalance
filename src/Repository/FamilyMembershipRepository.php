<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\FamilyMembership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FamilyMembership>
 */
final class FamilyMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FamilyMembership::class);
    }

    /**
     * @return FamilyMembership[]
     */
    public function findActiveMemberships(Family $family): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.family = :family')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('family', $family)
            ->orderBy('m.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveMembership(Family $family, User $user): ?FamilyMembership
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.family = :family')
            ->andWhere('m.user = :user')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('family', $family)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveMembershipForUser(User $user): ?FamilyMembership
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestMembershipForUser(User $user): ?FamilyMembership
    {
        return $this->createQueryBuilder('m')
            ->addSelect('f')
            ->join('m.family', 'f')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.joinedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
