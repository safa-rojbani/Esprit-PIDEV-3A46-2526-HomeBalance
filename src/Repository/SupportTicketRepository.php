<?php

namespace App\Repository;

use App\Entity\SupportTicket;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupportTicket>
 */
class SupportTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportTicket::class);
    }

    /**
     * @return SupportTicket[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SupportTicket[]
     */
    public function findAllOrdered(?string $statusFilter = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->orderBy('t.createdAt', 'DESC');

        if ($statusFilter !== null && $statusFilter !== '') {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $statusFilter);
        }

        return $qb->setMaxResults(200)
            ->getQuery()
            ->getResult();
    }
}
