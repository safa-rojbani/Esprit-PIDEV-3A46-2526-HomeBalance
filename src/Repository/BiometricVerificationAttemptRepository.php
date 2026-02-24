<?php

namespace App\Repository;

use App\Entity\BiometricVerificationAttempt;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BiometricVerificationAttempt>
 */
class BiometricVerificationAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BiometricVerificationAttempt::class);
    }

    public function countRecentFailures(User $actor, DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.actorUser = :actor')
            ->andWhere('a.result IN (:results)')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('actor', $actor)
            ->setParameter('results', [
                BiometricVerificationAttempt::RESULT_FAILED,
                BiometricVerificationAttempt::RESULT_ERROR,
            ])
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

