<?php

namespace App\Message\AI;

class SummarizeConversationMessage
{
    public function __construct(
        private readonly int $conversationId,
        private readonly string $userId,
        private readonly int $limit = 50,
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

    public function getLimit(): int
    {
        return $this->limit;
    }
}
