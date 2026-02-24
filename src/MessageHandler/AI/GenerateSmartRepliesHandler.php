<?php

namespace App\MessageHandler\AI;

use App\Entity\AiSmartReply;
use App\Entity\Conversation;
use App\Entity\User;
use App\Message\AI\GenerateSmartRepliesMessage;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use App\Service\AI\SmartReplyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GenerateSmartRepliesHandler
{
    public function __construct(
        private readonly SmartReplyService $smartReplyService,
        private readonly ConversationRepository $conversationRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HubInterface $hub,
    ) {
    }

    public function __invoke(GenerateSmartRepliesMessage $message): void
    {
        $conversation = $this->conversationRepository->find($message->getConversationId());
        $user = $this->userRepository->find($message->getUserId());

        if (!$conversation || !$user) {
            return;
        }

        // Generate suggestions
        $suggestions = $this->smartReplyService->suggestReplies($conversation, $user);

        if (empty($suggestions)) {
            // Don't persist empty suggestions, but still broadcast to clear any existing
            return;
        }

        // Persist the suggestions
        $aiSmartReply = new AiSmartReply();
        $aiSmartReply->setConversation($conversation);
        $aiSmartReply->setUser($user);
        $aiSmartReply->setSuggestions($suggestions);
        $aiSmartReply->setGeneratedAt(new \DateTimeImmutable());
        $aiSmartReply->setIsUsed(false);

        $this->entityManager->persist($aiSmartReply);
        $this->entityManager->flush();

        // Broadcast to user's personal topic via Mercure
        $this->broadcastSmartReplies($user, $conversation, $suggestions);
    }

    private function broadcastSmartReplies(User $user, Conversation $conversation, array $suggestions): void
    {
        $topic = sprintf('messaging/user/%s', $user->getId());
        
        $update = new Update(
            $topic,
            json_encode([
                'type' => 'smart_replies',
                'conversationId' => $conversation->getId(),
                'suggestions' => $suggestions,
                'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR),
            true // private topic
        );

        $this->hub->publish($update);
    }
}
