<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\SupportTicket;

final class SupportReplyService
{
    public function __construct(
        private readonly GeminiClient $geminiClient,
    ) {
    }

    /**
     * @return string[] Up to 3 reply suggestions
     */
    public function suggestReplies(SupportTicket $ticket): array
    {
        $systemPrompt = <<<'TXT'
You are a helpful support agent.

Given the full support ticket (subject, initial message and conversation), propose up to 3 short reply suggestions.
Return ONLY a JSON array of strings, no other text. Each string must be at most 200 characters.
TXT;

        $parts = [];
        $parts[] = 'Subject: ' . (string) $ticket->getSubject();
        $parts[] = "Initial message:\n" . (string) $ticket->getMessage();

        foreach ($ticket->getMessages() as $message) {
            $author = $message->getAuthor();
            $isAdmin = in_array('ROLE_ADMIN', $author->getRoles(), true);
            $role = $isAdmin ? 'AGENT' : 'USER';
            $parts[] = sprintf('%s: %s', $role, (string) $message->getContent());
        }

        $userContent = implode("\n\n", $parts);

        $raw = $this->geminiClient->generate('', $systemPrompt, $userContent);

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $trimmed = trim($raw);

            // If the model returned something that looks like a JSON array but with extra text,
            // try to extract the first array substring.
            if ($trimmed !== '' && $trimmed[0] === '[') {
                $data = json_decode($trimmed, true);
            }
        }

        // Fallback: treat the raw text as newline‑separated suggestions
        if (!is_array($data)) {
            $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
            $data = [];
            foreach ($lines as $line) {
                $line = trim($line, " \t\n\r\0\x0B-*•");
                if ($line === '') {
                    continue;
                }
                $data[] = $line;
            }
        }

        $suggestions = [];
        foreach ($data as $item) {
            if (!is_string($item)) {
                continue;
            }
            $text = trim($item);
            if ($text === '') {
                continue;
            }
            if (strlen($text) > 200) {
                $text = substr($text, 0, 197) . '...';
            }
            $suggestions[] = $text;
            if (count($suggestions) >= 3) {
                break;
            }
        }

        // Hard fallback: if AI failed or returned nothing, provide generic but useful replies
        if ($suggestions === []) {
            $username = $ticket->getUser()?->getUsername() ?? 'client';
            $subject = (string) $ticket->getSubject();

            $suggestions = [
                sprintf('Bonjour %s, merci pour votre message concernant "%s". Nous analysons votre demande et revenons vers vous rapidement.', $username, $subject),
                'Merci pour ces précisions. Nous allons vérifier les informations de votre compte et vous tiendrons informé dès que possible.',
                'Nous avons bien reçu votre demande et la transmettons à l’équipe concernée. Vous recevrez une réponse détaillée très prochainement.',
            ];
        }

        return $suggestions;
    }
}

