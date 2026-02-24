<?php

namespace App\Repository;

use App\Entity\ConversationParticipant;
use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConversationParticipant>
 */
class ConversationParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationParticipant::class);
    }

    public function isUserParticipant(Conversation $conversation, User $user): bool
    {
        $count = $this->createQueryBuilder('cp')
            ->select('COUNT(cp.id)')
            ->where('cp.conversation = :conversation')
            ->andWhere('cp.user = :user')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
