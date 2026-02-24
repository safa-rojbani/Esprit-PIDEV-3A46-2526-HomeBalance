<?php

namespace App\Message\SMS;

class RecalculateActivityPatternMessage
{
    public function __construct(
        private readonly string $userId,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
