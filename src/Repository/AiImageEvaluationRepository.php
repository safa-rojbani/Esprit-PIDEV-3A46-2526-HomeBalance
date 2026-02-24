<?php

namespace App\Repository;

use App\Entity\AiImageEvaluation;
use App\Entity\TaskCompletion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiImageEvaluation>
 */
class AiImageEvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiImageEvaluation::class);
    }

    public function findOneByCompletion(TaskCompletion $completion): ?AiImageEvaluation
    {
        return $this->createQueryBuilder('aie')
            ->andWhere('aie.completion = :completion')
            ->setParameter('completion', $completion)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

