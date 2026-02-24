<?php

namespace App\Service\AI;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Exception\HuggingFaceException;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class SmartReplyService
{
    private const MODEL = 'facebook/blenderbot-400M-distill';
    private const MAX_SUGGESTIONS = 3;
    private const MAX_SUGGESTION_LENGTH = 100;
    private const MESSAGE_LIMIT = 5;

    public function __construct(
        private readonly HuggingFaceClient $huggingFaceClient,
        private readonly MessageRepository $messageRepository,
    ) {
    }

    /**
     * Generate smart reply suggestions for a conversation.
     *
     * @return array Array of suggestion strings (max 3, max 100 chars each)
     */
    public function suggestReplies(Conversation $conversation, User $currentUser): array
    {
        try {
            $messages = $this->messageRepository->findMessagesByConversation($conversation);
            
            // Get last N messages, exclude deleted ones
            $recentMessages = array_filter(
                array_slice($messages, -self::MESSAGE_LIMIT),
                fn (Message $m) => $m->getContent() !== null && $m->getContent() !== ''
            );

            if (empty($recentMessages)) {
                return [];
            }

            // Format conversation context
            $conversationContext = $this->formatConversationContext($recentMessages);

            // Call Hugging Face API
            $response = $this->huggingFaceClient->query(self::MODEL, [
                'inputs' => $conversationContext,
            ]);

            // Parse response and extract suggestions
            return $this->parseSuggestions($response);

        } catch (HuggingFaceException $e) {
            // Log the error but don't crash the UI
            // Return empty array to degrade silently
            return [];
        } catch (\Throwable $e) {
            // Any other error, degrade silently
            return [];
        }
    }

    /**
     * Format recent messages as conversation context.
     *
     * @param Message[] $messages
     */
    private function formatConversationContext(array $messages): string
    {
        $context = '';
        
        foreach ($messages as $message) {
            $sender = $message->getSender();
            $senderName = $sender 
                ? ($sender->getFirstName() ?? 'User') 
                : 'User';
            $content = $message->getContent() ?? '';
            
            $context .= "{$senderName}: {$content}\n";
        }

        return $context;
    }

    /**
     * Parse the Hugging Face response to extract suggestions.
     *
     * @param array $response
     * @return array
     */
    private function parseSuggestions(array $response): array
    {
        $suggestions = [];

        // The response format from blenderbot is typically:
        // [{'generated_text': '...'}]
        if (isset($response[0]['generated_text'])) {
            $generatedText = $response[0]['generated_text'];
            
            // The response may contain the conversation context followed by the reply
            // We need to extract just the reply part
            
            // Try to split by common patterns and extract the last statement
            $lines = preg_split('/\n|\. /', $generatedText);
            
            if (!empty($lines)) {
                // Get the last few meaningful segments
                $lastSegments = array_slice($lines, -3);
                
                foreach ($lastSegments as $segment) {
                    $segment = trim($segment);
                    if (strlen($segment) > 0 && strlen($segment) <= self::MAX_SUGGESTION_LENGTH) {
                        $suggestions[] = $segment;
                    } elseif (strlen($segment) > self::MAX_SUGGESTION_LENGTH) {
                        // Truncate if too long
                        $suggestions[] = substr($segment, 0, self::MAX_SUGGESTION_LENGTH - 3) . '...';
                    }
                }
            }
        }

        // Ensure we have valid suggestions
        $validSuggestions = array_filter($suggestions, fn($s) => strlen(trim($s)) > 0);
        
        // Return up to MAX_SUGGESTIONS
        return array_slice(array_values($validSuggestions), 0, self::MAX_SUGGESTIONS);
    }
}
