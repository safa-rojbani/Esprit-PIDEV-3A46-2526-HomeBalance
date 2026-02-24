<?php

namespace App\Message\AI;

class GenerateSmartRepliesMessage
{
    public function __construct(
        private readonly int $conversationId,
        private readonly int $userId,
    ) {
    }

    public function getConversationId(): int
    {
        return $this->conversationId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
