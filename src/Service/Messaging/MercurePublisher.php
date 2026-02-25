<?php
declare(strict_types=1);
namespace App\Service\Messaging;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercurePublisher
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    public function publishNewMessage(Message $message): void
    {
        $conversation  = $message->getConversation();
        $sender        = $message->getSender();
        $parent        = $message->getParentMessage();

        $parentData = null;
        if ($parent !== null) {
            $parentSender = $parent->getSender();
            $parentData   = [
                'messageId'  => $parent->getId(),
                'senderName' => $parentSender ? ($parentSender->getFirstName() . ' ' . $parentSender->getLastName()) : 'Unknown',
                'content'    => mb_substr((string) $parent->getContent(), 0, 120),
            ];
        }

        $this->publish(
            $this->conversationTopic($conversation),
            [
                'type'           => 'new_message',
                'messageId'      => $message->getId(),
                'conversationId' => $conversation->getId(),
                'senderId'       => $sender?->getId(),
                'senderName'     => $sender ? ($sender->getFirstName() . ' ' . $sender->getLastName()) : 'Unknown',
                'senderAvatar'   => $sender?->getAvatarPath(),
                'content'        => $message->getContent(),
                'sentAt'         => $message->getSentAt()?->format(\DateTimeInterface::ATOM),
                'isRead'         => $message->isRead(),
                'attachmentURL'  => $message->getAttachmentURL(),
                'parent'         => $parentData,
            ],
            $this->participantTopics($conversation),
        );
    }

    public function publishTyping(Conversation $conversation, User $user, bool $isTyping): void
    {
        $this->publish(
            $this->conversationTopic($conversation),
            [
                'type'           => 'typing',
                'conversationId' => $conversation->getId(),
                'userId'         => $user->getId(),
                'userName'       => $user->getFirstName(),
                'isTyping'       => $isTyping,
            ],
            $this->participantTopics($conversation),
        );
    }

    public function publishReadReceipt(Message $message, User $reader): void
    {
        $conversation = $message->getConversation();
        $this->publish(
            $this->conversationTopic($conversation),
            [
                'type'           => 'read_receipt',
                'conversationId' => $conversation->getId(),
                'messageId'      => $message->getId(),
                'readerId'       => $reader->getId(),
                'readerName'     => $reader->getFirstName(),
                'readAt'         => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
            $this->participantTopics($conversation),
        );
    }

    public function publishReactionUpdate(Message $message, array $reactions): void
    {
        $conversation = $message->getConversation();
        $this->publish(
            $this->conversationTopic($conversation),
            [
                'type'           => 'reaction_update',
                'messageId'      => $message->getId(),
                'conversationId' => $conversation->getId(),
                'reactions'      => $reactions,
            ],
            $this->participantTopics($conversation),
        );
    }

    public function publishPresence(User $user, bool $online): void
    {
        $this->publish(
            $this->userPresenceTopic($user),
            [
                'type'   => 'presence',
                'userId' => $user->getId(),
                'online' => $online,
                'at'     => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
            [$this->userPresenceTopic($user)],
        );
    }

    public function conversationTopic(Conversation $conversation): string
    {
        return sprintf('messaging/conversation/%s', $conversation->getId());
    }

    public function userPresenceTopic(User $user): string
    {
        return sprintf('messaging/user/%s/presence', $user->getId());
    }

    public function participantTopics(Conversation $conversation): array
    {
        $topics = [$this->conversationTopic($conversation)];
        foreach ($conversation->getConversationParticipants() as $participant) {
            $user = $participant->getUser();
            if ($user !== null) {
                $topics[] = sprintf('messaging/user/%s', $user->getId());
            }
        }
        return $topics;
    }

    private function publish(string $topic, array $data, array $privateTopics = []): void
    {
        $update = new Update(
            $topic,
            json_encode($data, JSON_THROW_ON_ERROR),
            $privateTopics !== [] ? $privateTopics : false,
        );
        $this->hub->publish($update);
    }
}
