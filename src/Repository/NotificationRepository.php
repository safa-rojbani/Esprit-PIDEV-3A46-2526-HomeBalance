<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnreadForRecipientInFamily(User $recipient, Family $family): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.family = :family')
            ->andWhere('n.isRead = false')
            ->setParameter('recipient', $recipient)
            ->setParameter('family', $family)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestUnreadForRecipientInFamily(User $recipient, Family $family): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->addSelect('actor')
            ->leftJoin('n.actor', 'actor')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.family = :family')
            ->andWhere('n.isRead = false')
            ->setParameter('recipient', $recipient)
            ->setParameter('family', $family)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Notification[]
     */
    public function findAllForRecipientInFamily(User $recipient, Family $family): array
    {
        return $this->createQueryBuilder('n')
            ->addSelect('actor')
            ->leftJoin('n.actor', 'actor')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.family = :family')
            ->setParameter('recipient', $recipient)
            ->setParameter('family', $family)
            ->orderBy('n.isRead', 'ASC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
