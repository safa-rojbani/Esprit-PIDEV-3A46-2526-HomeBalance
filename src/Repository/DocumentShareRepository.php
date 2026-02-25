<?php

namespace App\Repository;

use App\Entity\DocumentShare;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentShare>
 */
class DocumentShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentShare::class);
    }

    public function findOneByRawToken(string $rawToken): ?DocumentShare
    {
        return $this->findOneBy([
            'tokenHash' => hash('sha256', $rawToken),
        ]);
    }

    public function countSharesByUserSince(User $user, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('share')
            ->select('COUNT(share.id)')
            ->andWhere('share.sharedBy = :user')
            ->andWhere('share.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOldestShareByUserSince(User $user, \DateTimeImmutable $since): ?DocumentShare
    {
        return $this->createQueryBuilder('share')
            ->andWhere('share.sharedBy = :user')
            ->andWhere('share.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->orderBy('share.createdAt', 'ASC')
            ->addOrderBy('share.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
