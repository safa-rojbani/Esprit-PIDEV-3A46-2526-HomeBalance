<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\SupportTicket;

final class SupportTicketClassificationService
{
    public function __construct(
        private readonly GeminiClient $geminiClient,
    ) {
    }

    /**
     * @return array{category: string|null, priority: string|null}
     */
    public function classify(SupportTicket $ticket): array
    {
        $systemPrompt = <<<'TXT'
You are a support triage assistant.

Given a support ticket subject and message, respond with a pure JSON object, no explanation:
{
  "category": one of ["billing","technical","account","other"],
  "priority": one of ["low","medium","high"]
}
TXT;

        $userContent = sprintf(
            "Subject: %s\n\nMessage:\n%s",
            (string) $ticket->getSubject(),
            (string) $ticket->getMessage(),
        );

        $raw = $this->geminiClient->generate('', $systemPrompt, $userContent);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return ['category' => null, 'priority' => null];
        }

        return [
            'category' => isset($data['category']) && is_string($data['category']) ? $data['category'] : null,
            'priority' => isset($data['priority']) && is_string($data['priority']) ? $data['priority'] : null,
        ];
    }
}

