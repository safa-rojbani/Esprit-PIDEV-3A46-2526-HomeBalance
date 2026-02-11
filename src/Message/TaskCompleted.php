<?php

namespace App\Message;

final class TaskCompleted
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {
    }
}
