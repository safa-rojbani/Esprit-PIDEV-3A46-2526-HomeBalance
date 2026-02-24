<?php

namespace App\Repository;

use App\Entity\AiConversationSummary;
use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiConversationSummary>
 */
class AiConversationSummaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiConversationSummary::class);
    }

    /**
     * Find the latest summary for a conversation requested by a user.
     */
    public function findLatestForUserAndConversation(User $user, Conversation $conversation): ?AiConversationSummary
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.requestedBy = :user')
            ->andWhere('a.conversation = :conversation')
            ->setParameter('user', $user)
            ->setParameter('conversation', $conversation)
            ->orderBy('a.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
