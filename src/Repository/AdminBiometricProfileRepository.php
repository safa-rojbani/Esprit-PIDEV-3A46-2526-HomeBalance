<?php

namespace App\Repository;

use App\Entity\AdminBiometricProfile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminBiometricProfile>
 */
class AdminBiometricProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminBiometricProfile::class);
    }

    public function findEnabledForUser(User $user): ?AdminBiometricProfile
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.enabled = :enabled')
            ->setParameter('user', $user)
            ->setParameter('enabled', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

