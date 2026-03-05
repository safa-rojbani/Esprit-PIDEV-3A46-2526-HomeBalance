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
    public function findAllForRecipientInFamily(User $recipient, Family $family, ?int $limit = null): array
    {
        if ($limit !== null) {
            $limit = max(1, min($limit, 100));
        }

        $idsQb = $this->createQueryBuilder('n')
            ->select('n.id AS id')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.family = :family')
            ->setParameter('recipient', $recipient)
            ->setParameter('family', $family)
            ->orderBy('n.isRead', 'ASC')
            ->addOrderBy('n.createdAt', 'DESC');

        if ($limit !== null) {
            $idsQb->setMaxResults($limit);
        }

        $idRows = $idsQb
            ->getQuery()
            ->getScalarResult();

        if ($idRows === []) {
            return [];
        }

        $orderedIds = array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $idRows
        );
        $orderedIds = array_values(array_filter($orderedIds, static fn (int $id): bool => $id > 0));

        if ($orderedIds === []) {
            return [];
        }

        /** @var array<int, Notification> $notificationsById */
        $notificationsById = $this->createQueryBuilder('n', 'n.id')
            ->addSelect('actor')
            ->leftJoin('n.actor', 'actor')
            ->andWhere('n.id IN (:ids)')
            ->setParameter('ids', $orderedIds)
            ->getQuery()
            ->getResult();

        $ordered = [];
        foreach ($orderedIds as $id) {
            if (isset($notificationsById[$id])) {
                $ordered[] = $notificationsById[$id];
            }
        }

        return $ordered;
    }
}
