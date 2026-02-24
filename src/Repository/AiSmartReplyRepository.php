<?php

namespace App\Repository;

use App\Entity\AiSmartReply;
use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiSmartReply>
 */
class AiSmartReplyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiSmartReply::class);
    }

    /**
     * Find the latest smart reply suggestions for a user in a conversation.
     */
    public function findLatestForUserAndConversation(User $user, Conversation $conversation): ?AiSmartReply
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.conversation = :conversation')
            ->setParameter('user', $user)
            ->setParameter('conversation', $conversation)
            ->orderBy('a.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find unused smart reply suggestions for a user in a conversation.
     */
    public function findUnusedForUserAndConversation(User $user, Conversation $conversation): ?AiSmartReply
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.conversation = :conversation')
            ->andWhere('a.isUsed = :isUsed')
            ->setParameter('user', $user)
            ->setParameter('conversation', $conversation)
            ->setParameter('isUsed', false)
            ->orderBy('a.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
