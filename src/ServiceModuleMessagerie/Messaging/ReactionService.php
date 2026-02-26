<?php

declare(strict_types=1);

namespace App\ServiceModuleMessagerie\Messaging;

use App\Entity\Message;
use App\Entity\MessageReaction;
use App\Entity\User;
use App\Repository\MessageReactionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ReactionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageReactionRepository $reactionRepository,
        private readonly MercurePublisher $mercurePublisher,
    ) {
    }

    /**
     * Toggle a reaction: add if not present, remove if already present.
     *
     * @return array{action: 'added'|'removed', reactions: array<string, array{count: int, users: array<string, string>}>}
     * @throws \InvalidArgumentException if the emoji is not in the allowed set
     */
    public function toggle(Message $message, User $user, string $emoji): array
    {
        if (!in_array($emoji, MessageReaction::ALLOWED_EMOJIS, true)) {
            throw new \InvalidArgumentException(sprintf('Emoji "%s" is not allowed.', $emoji));
        }

        $existing = $this->reactionRepository->findOneByMessageUserEmoji($message, $user, $emoji);

        if ($existing !== null) {
            $this->entityManager->remove($existing);
            $action = 'removed';
        } else {
            $reaction = (new MessageReaction())
                ->setMessage($message)
                ->setUser($user)
                ->setEmoji($emoji);
            $this->entityManager->persist($reaction);
            $action = 'added';
        }

        $this->entityManager->flush();

        $reactions = $this->reactionRepository->groupedByEmoji($message);

        // Broadcast to all conversation participants
        try {
            $this->mercurePublisher->publishReactionUpdate($message, $reactions);
        } catch (\Throwable) {
        }

        return [
            'action'    => $action,
            'reactions' => $reactions,
        ];
    }
}
