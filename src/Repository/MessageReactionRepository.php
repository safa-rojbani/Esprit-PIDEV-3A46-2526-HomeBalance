<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Message;
use App\Entity\MessageReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageReaction>
 */
class MessageReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageReaction::class);
    }

    /**
     * Find a specific reaction by message + user + emoji (for toggle logic).
     */
    public function findOneByMessageUserEmoji(Message $message, User $user, string $emoji): ?MessageReaction
    {
        return $this->findOneBy([
            'message' => $message,
            'user'    => $user,
            'emoji'   => $emoji,
        ]);
    }

    /**
     * Return all reactions for a message, grouped as:
     * [ emoji => ['count' => int, 'users' => [userId => firstName, ...]] ]
     *
     * @return array<string, array{count: int, users: array<string, string>}>
     */
    public function groupedByEmoji(Message $message): array
    {
        $reactions = $this->findBy(['message' => $message]);

        $grouped = [];
        foreach ($reactions as $reaction) {
            $emoji = $reaction->getEmoji();
            $user  = $reaction->getUser();
            if ($user === null) {
                continue;
            }

            $grouped[$emoji] ??= ['count' => 0, 'users' => []];
            $grouped[$emoji]['count']++;
            $grouped[$emoji]['users'][(string) $user->getId()] = $user->getFirstName() ?? '';
        }

        return $grouped;
    }

    /**
     * Return grouped reactions for a list of message IDs in one query.
     *
     * @param list<int> $messageIds
     * @return array<int, array<string, array{count: int, users: array<string, string>}>>
     */
    public function groupedByEmojiForMessages(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $reactions = $this->createQueryBuilder('r')
            ->join('r.message', 'm')
            ->join('r.user', 'u')
            ->addSelect('m', 'u')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $messageIds)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($reactions as $reaction) {
            $msgId = $reaction->getMessage()?->getId();
            $emoji = $reaction->getEmoji();
            $user  = $reaction->getUser();
            if ($msgId === null || $user === null) {
                continue;
            }

            $result[$msgId][$emoji] ??= ['count' => 0, 'users' => []];
            $result[$msgId][$emoji]['count']++;
            $result[$msgId][$emoji]['users'][(string) $user->getId()] = $user->getFirstName() ?? '';
        }

        return $result;
    }
}
