<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Conversation;
use App\Enum\TypeConversation;
use DateTimeImmutable;
use InvalidArgumentException;

final class ConversationManager
{
    /**
     * Validate Conversation business rules.
     *
     * Rules:
     * 1) createdAt must not be in the future.
     * 2) A group conversation cannot use the reserved name "Private Chat".
     */
    public function validate(Conversation $conversation): void
    {
        $name = trim((string) $conversation->getConversationName());
        if ($name === '') {
            throw new InvalidArgumentException('Conversation name is required.');
        }

        $createdAt = $conversation->getCreatedAt();
        if ($createdAt === null) {
            throw new InvalidArgumentException('Conversation createdAt is required.');
        }

        if ($createdAt > new DateTimeImmutable()) {
            throw new InvalidArgumentException('Conversation createdAt cannot be in the future.');
        }

        $type = $conversation->getType();
        if ($type === null) {
            throw new InvalidArgumentException('Conversation type is required.');
        }

        if ($type === TypeConversation::GROUP && strcasecmp($name, 'Private Chat') === 0) {
            throw new InvalidArgumentException('A group conversation cannot be named "Private Chat".');
        }
    }
}
