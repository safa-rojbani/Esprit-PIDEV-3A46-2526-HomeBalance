<?php

namespace App\Message\AI;

class GenerateSmartRepliesMessage
{
    public function __construct(
        private readonly int $conversationId,
        private readonly string $userId,
    ) {
    }

    public function getConversationId(): int
    {
        return $this->conversationId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
