<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\Conversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function findMessagesByConversation(Conversation $conversation): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param \App\Entity\User $user
     * @return array<int, int> Map of conversationId => unreadCount
     */
    public function countUnreadForUserByConversation(\App\Entity\User $user): array
    {
        $results = $this->createQueryBuilder('m')
            ->select('c.id as conversationId, COUNT(m.id) as unreadCount')
            ->innerJoin('m.conversation', 'c')
            ->innerJoin('c.conversationParticipants', 'cp')
            ->where('cp.user = :user')
            ->andWhere('m.sender != :user')
            ->andWhere('m.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($results as $res) {
            $map[$res['conversationId']] = (int)$res['unreadCount'];
        }

        return $map;
    }
}
