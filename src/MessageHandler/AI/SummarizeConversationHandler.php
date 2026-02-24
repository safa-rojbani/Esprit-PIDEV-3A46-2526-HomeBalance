<?php

namespace App\MessageHandler\AI;

use App\Entity\AiConversationSummary;
use App\Entity\Conversation;
use App\Entity\User;
use App\Exception\HuggingFaceException;
use App\Message\AI\SummarizeConversationMessage;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use App\Service\AI\SummarizationService;
use App\Service\AuditTrailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SummarizeConversationHandler
{
    public function __construct(
        private readonly SummarizationService $summarizationService,
        private readonly ConversationRepository $conversationRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HubInterface $hub,
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    public function __invoke(SummarizeConversationMessage $message): void
    {
        $conversation = $this->conversationRepository->find($message->getConversationId());
        $user = $this->userRepository->find($message->getUserId());

        if (!$conversation || !$user) {
            return;
        }

        try {
            // Generate summary
            $summary = $this->summarizationService->summarize($conversation, $message->getLimit());
            $messageCount = $message->getLimit();

            // Persist the summary
            $aiSummary = new AiConversationSummary();
            $aiSummary->setConversation($conversation);
            $aiSummary->setRequestedBy($user);
            $aiSummary->setSummary($summary);
            $aiSummary->setMessageCount($messageCount);
            $aiSummary->setGeneratedAt(new \DateTimeImmutable());

            $this->entityManager->persist($aiSummary);
            $this->entityManager->flush();

            // Record audit trail
            $this->auditTrailService->record(
                $user,
                'ai.summary.generated',
                [
                    'conversationId' => $conversation->getId(),
                    'messageCount' => $messageCount,
                ],
                $conversation->getFamily()
            );

            // Broadcast to user's personal topic via Mercure
            $this->broadcastSummary($user, $conversation, $summary, $messageCount);

        } catch (HuggingFaceException $e) {
            // Re-throw so Messenger can retry
            throw $e;
        }
    }

    private function broadcastSummary(User $user, Conversation $conversation, string $summary, int $messageCount): void
    {
        $topic = sprintf('messaging/user/%s', $user->getId());
        
        $update = new Update(
            $topic,
            json_encode([
                'type' => 'conversation_summary',
                'conversationId' => $conversation->getId(),
                'summary' => $summary,
                'messageCount' => $messageCount,
                'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR),
            true // private topic
        );

        $this->hub->publish($update);
    }
}
