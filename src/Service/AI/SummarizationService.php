<?php

namespace App\Service\AI;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Exception\HuggingFaceException;
use App\Repository\MessageRepository;

class SummarizationService
{
    private const MODEL = 'facebook/bart-large-cnn';
    private const MAX_LENGTH = 1024;
    private const MIN_LENGTH = 30;

    public function __construct(
        private readonly HuggingFaceClient $huggingFaceClient,
        private readonly MessageRepository $messageRepository,
    ) {
    }

    /**
     * Summarize the last N messages in a conversation.
     *
     * @param Conversation $conversation
     * @param int $limit Number of messages to summarize (default 50)
     * @return string The summary text
     * @throws HuggingFaceException On failure (so handler can retry)
     */
    public function summarize(Conversation $conversation, int $limit = 50): string
    {
        $messages = $this->messageRepository->findMessagesByConversation($conversation);
        
        // Get last N messages, exclude deleted ones
        $recentMessages = array_filter(
            array_slice($messages, -$limit),
            fn (Message $m) => $m->getContent() !== null && $m->getContent() !== ''
        );

        if (empty($recentMessages)) {
            throw new HuggingFaceException('No messages to summarize');
        }

        // Format messages with sender name prefix
        $textToSummarize = $this->formatMessages($recentMessages);

        // Call Hugging Face API
        $response = $this->huggingFaceClient->query(self::MODEL, [
            'inputs' => $textToSummarize,
            'parameters' => [
                'max_length' => self::MAX_LENGTH,
                'min_length' => self::MIN_LENGTH,
                'do_sample' => false,
            ],
        ]);

        // Parse response
        return $this->parseSummary($response);
    }

    /**
     * Format messages for summarization.
     *
     * @param Message[] $messages
     */
    private function formatMessages(array $messages): string
    {
        $text = '';
        
        foreach ($messages as $message) {
            $sender = $message->getSender();
            $senderName = $sender 
                ? ($sender->getFirstName() ?? 'User') 
                : 'User';
            $content = $message->getContent() ?? '';
            
            $text .= "{$senderName}: {$content}\n";
        }

        return $text;
    }

    /**
     * Parse the Hugging Face response to extract the summary.
     *
     * @param array $response
     * @return string
     */
    private function parseSummary(array $response): string
    {
        // The response format from bart-large-cnn is typically:
        // [{'summary_text': '...'}]
        if (isset($response[0]['summary_text'])) {
            return $response[0]['summary_text'];
        }

        throw new HuggingFaceException('Unexpected response format from summarization model');
    }
}
