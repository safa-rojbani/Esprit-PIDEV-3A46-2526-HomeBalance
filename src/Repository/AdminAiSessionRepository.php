<?php

namespace App\Repository;

use App\Entity\AdminAiSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminAiSession>
 */
class AdminAiSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminAiSession::class);
    }

    /**
     * @return list<AdminAiSession>
     */
    public function findRecentForActor(User $actor, int $limit = 20): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.actorUser = :actor')
            ->setParameter('actor', $actor)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

