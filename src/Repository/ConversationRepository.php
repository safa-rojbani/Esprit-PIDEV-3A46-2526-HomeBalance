<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use App\Enum\TypeConversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findUserConversations(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->select('c')
            ->innerJoin('c.conversationParticipants', 'cp')
            ->leftJoin('c.messages', 'm')
            ->where('cp.user = :user')
            ->andWhere('c.type IN (:types)')
            ->setParameter('user', $user)
            ->setParameter('types', [TypeConversation::PRIVATE , TypeConversation::GROUP])
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC') // Simple sort first
            ->getQuery()
            ->getResult();
    }

    public function findPrivateConversationBetween(User $user1, User $user2): ?Conversation
    {
        // specific logic to find a private conversation with exactly these two participants
        // This is tricky in pure DQL without complex subqueries. 
        // A simpler approach: find conversations of user1, then filter for user2 in PHP or a slightly more complex query.

        $qb = $this->createQueryBuilder('c');

        return $qb
            ->innerJoin('c.conversationParticipants', 'cp1')
            ->innerJoin('c.conversationParticipants', 'cp2')
            ->where('cp1.user = :user1')
            ->andWhere('cp2.user = :user2')
            ->andWhere('c.type = :type')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->setParameter('type', TypeConversation::PRIVATE )
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
