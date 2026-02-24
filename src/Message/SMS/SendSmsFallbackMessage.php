<?php

namespace App\Message\SMS;

class SendSmsFallbackMessage
{
    public function __construct(
        private readonly string $recipientUserId,
        private readonly string $senderName,
        private readonly int $conversationId,
        private readonly string $messagePreview,
        private readonly \DateTimeImmutable $sentAt,
    ) {
    }

    public function getRecipientUserId(): string
    {
        return $this->recipientUserId;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function getConversationId(): int
    {
        return $this->conversationId;
    }

    public function getMessagePreview(): string
    {
        return $this->messagePreview;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
