<?php

declare(strict_types=1);

namespace App\Service\Messaging;

use App\Entity\Conversation;
use App\Entity\User;

/**
 * Generates subscriber JWT tokens for the browser so it can open a
 * JWT-secured SSE connection to the Mercure hub.
 *
 * The token encodes the list of topics the user is allowed to subscribe to.
 * We use HS256 (HMAC-SHA256) with the same secret as the hub.
 */
final class MercureTokenFactory
{
    public function __construct(
        private readonly string $jwtSecret,
        private readonly MercurePublisher $publisher,
    ) {
    }

    /**
     * Build a subscriber JWT for a user that covers:
     *  - their personal presence topic
     *  - all topics for the given conversation (if provided)
     *
     * @return string  Signed JWT
     */
    public function buildSubscriberToken(User $user, ?Conversation $conversation = null): string
    {
        $topics = [
            $this->publisher->userPresenceTopic($user),
            sprintf('messaging/user/%s', $user->getId()),
        ];

        if ($conversation !== null) {
            $topics[] = $this->publisher->conversationTopic($conversation);
        }

        return $this->sign(['mercure' => ['subscribe' => $topics]]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Minimal HS256 JWT implementation (no external dependency)
    // ──────────────────────────────────────────────────────────────────────────

    private function sign(array $payload): string
    {
        $header  = $this->base64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $body    = $this->base64url(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig     = $this->base64url(hash_hmac('sha256', $header . '.' . $body, $this->jwtSecret, true));

        return $header . '.' . $body . '.' . $sig;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
