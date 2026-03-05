<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Conversation;
use App\Enum\TypeConversation;
use App\Service\ConversationManager;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConversationManagerTest extends TestCase
{
    #[Test]
    public function validateAcceptsValidConversation(): void
    {
        $conversation = (new Conversation())
            ->setConversationName('Private Chat')
            ->setType(TypeConversation::PRIVATE)
            ->setCreatedAt(new DateTimeImmutable('-1 hour'));

        $manager = new ConversationManager();

        $manager->validate($conversation);

        self::assertTrue(true);
    }

    #[Test]
    public function validateThrowsWhenCreatedAtIsInFuture(): void
    {
        $conversation = (new Conversation())
            ->setConversationName('Chat famille')
            ->setType(TypeConversation::GROUP)
            ->setCreatedAt(new DateTimeImmutable('+1 day'));

        $manager = new ConversationManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Conversation createdAt cannot be in the future.');

        $manager->validate($conversation);
    }

    #[Test]
    public function validateThrowsWhenGroupConversationUsesPrivateChatName(): void
    {
        $conversation = (new Conversation())
            ->setConversationName('Private Chat')
            ->setType(TypeConversation::GROUP)
            ->setCreatedAt(new DateTimeImmutable('-1 day'));

        $manager = new ConversationManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A group conversation cannot be named "Private Chat".');

        $manager->validate($conversation);
    }
}
