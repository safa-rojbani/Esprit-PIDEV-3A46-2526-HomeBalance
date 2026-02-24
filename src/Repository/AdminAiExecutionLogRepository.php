<?php

namespace App\Repository;

use App\Entity\AdminAiExecutionLog;
use App\Entity\AdminAiSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminAiExecutionLog>
 */
class AdminAiExecutionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminAiExecutionLog::class);
    }

    /**
     * @return list<AdminAiExecutionLog>
     */
    public function findForSession(AdminAiSession $session): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.session = :session')
            ->setParameter('session', $session)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

