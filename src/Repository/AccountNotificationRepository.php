<?php

namespace App\Repository;

use App\Entity\AccountNotification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountNotification>
 */
final class AccountNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountNotification::class);
    }

    /**
     * @return AccountNotification[]
     */
    public function findRecent(?string $status = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('n')
            ->addSelect('u')
            ->join('n.user', 'u')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null) {
            $qb->andWhere('n.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return AccountNotification[]
     */
    public function findRecentForUser(User $user, int $limit = 20, ?string $channel = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($channel !== null) {
            $qb->andWhere('n.channel = :channel')
                ->setParameter('channel', $channel);
        }

        return $qb->getQuery()->getResult();
    }
}
