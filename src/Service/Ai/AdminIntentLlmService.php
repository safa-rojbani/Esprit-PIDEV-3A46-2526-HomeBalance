<?php

namespace App\Service\Ai;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AdminIntentLlmService
{
    private const GROQ_CHAT_COMPLETIONS_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $groqApiKey,
        private readonly string $model,
    ) {
    }

    /**
     * @return array{intent: string, filters: array<string, mixed>, limit: int, reason: string, user_id: ?string, fallback_used: bool}
     */
    public function planFromPrompt(string $prompt): array
    {
        $prompt = $this->sanitizePrompt($prompt);
        if ($prompt === '') {
            return $this->fallbackParse($prompt, true);
        }

        $cacheKey = 'admin_intent_llm_' . sha1($prompt);
        $cacheAllowed = !$this->containsSensitivePromptData($prompt);

        if ($cacheAllowed) {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($prompt): array {
                $item->expiresAfter(300);

                return $this->planFromPromptUncached($prompt);
            });
        }

        return $this->planFromPromptUncached($prompt);
    }

    private function buildInstruction(string $prompt): string
    {
        return <<<TXT
You are a helpful, natural administrative assistant for a Symfony UMS.
Your task: convert the admin request into ONE strict JSON object and nothing else.

STRICT RULES:
- Output valid JSON only (no markdown, no prose).
- Use only this schema:
{
  "intent": "one allowed intent",
  "filters": { "allowed_filter_key": "value" },
  "limit": integer 1..100,
  "reason": "short natural reason"
}
- Never invent intents or filter keys.
- If ambiguous, choose the safest and most common intent: "search_users_with_risk_filters".
- Infer reasonable defaults to feel natural.

ALLOWED INTENTS:
1) bulk_suspend_users
- Suspend multiple users based on risk/activity filters.
2) bulk_reactivate_users
- Reactivate suspended/inactive users based on filters.
3) reset_user_password
- Reset password for one user (or filtered users when clearly requested).
4) search_users_with_risk_filters
- Search users with risk/activity filters and return candidates.
5) export_audit_for_user
- Prepare audit export for one user (or filtered users if explicit).
- IMPORTANT: for reset_user_password and export_audit_for_user, include a specific target via filters.email.
- If the request is broad ("export audits for risky users"), choose search_users_with_risk_filters instead.

ALLOWED FILTER KEYS:
- status: string in {ACTIVE, INACTIVE, SUSPENDED, ALL}; default ALL.
- failed_logins_last_days: integer; default 30.
- password_changes_last_days: integer; default 30.
- min_password_changes: integer; default 0.
- email: string or array of strings.
- query: string free text search.
- last_active_before_days: integer (inactivity threshold).
- risk_score_above: integer.

FEW-SHOT EXAMPLES:
User: "Suspend users with lots of failed logins this week"
Assistant: {"intent":"bulk_suspend_users","filters":{"failed_logins_last_days":7},"limit":100,"reason":"high failed login attempts"}

User: "Find and reactivate all inactive users from the last 3 months"
Assistant: {"intent":"bulk_reactivate_users","filters":{"status":"INACTIVE","last_active_before_days":90},"limit":100,"reason":"reactivate dormant accounts"}

User: "Reset password for john.doe@example.com because he forgot it"
Assistant: {"intent":"reset_user_password","filters":{"email":"john.doe@example.com"},"limit":1,"reason":"forgotten password"}

User: "Export audit for john.doe@example.com"
Assistant: {"intent":"export_audit_for_user","filters":{"email":"john.doe@example.com"},"limit":1,"reason":"compliance export for target user"}

User: "Search users who haven't logged in recently"
Assistant: {"intent":"search_users_with_risk_filters","filters":{"last_active_before_days":30},"limit":100,"reason":"inactivity check"}

User: "Handle failed logins"
Assistant: {"intent":"search_users_with_risk_filters","filters":{"failed_logins_last_days":30},"limit":100,"reason":"review failed login activity"}

User: "Do something with all users"
Assistant: {"intent":"search_users_with_risk_filters","filters":{"status":"ALL"},"limit":100,"reason":"ambiguous request, safest search action"}

Now convert this request:
{$prompt}
TXT;
    }

    private function extractJson(string $value): ?string
    {
        $start = strpos($value, '{');
        $end = strrpos($value, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($value, $start, $end - $start + 1);
    }

    /**
     * @return array{intent: string, filters: array<string, mixed>, limit: int, reason: string, user_id: ?string, fallback_used: bool}
     */
    private function fallbackParse(string $prompt, bool $fallbackUsed): array
    {
        $lower = mb_strtolower($prompt);
        $email = $this->extractEmail($prompt);

        $intent = AdminIntentCatalog::INTENT_SEARCH_USERS_WITH_RISK_FILTERS;
        $filters = [];
        $limit = 100;

        if (str_contains($lower, 'suspend')) {
            $intent = AdminIntentCatalog::INTENT_BULK_SUSPEND_USERS;
            $filters['failed_logins_last_days'] = str_contains($lower, 'week') ? 7 : 30;
        } elseif (str_contains($lower, 'reactivat')) {
            $intent = AdminIntentCatalog::INTENT_BULK_REACTIVATE_USERS;
            $filters['status'] = 'INACTIVE';
        } elseif (
            str_contains($lower, 'reset')
            && (str_contains($lower, 'password') || str_contains($lower, 'pass'))
        ) {
            $intent = AdminIntentCatalog::INTENT_RESET_USER_PASSWORD;
            $limit = 1;
        } elseif (str_contains($lower, 'export') && str_contains($lower, 'audit')) {
            if ($email !== null) {
                $intent = AdminIntentCatalog::INTENT_EXPORT_AUDIT_FOR_USER;
                $limit = 1;
            } else {
                $intent = AdminIntentCatalog::INTENT_SEARCH_USERS_WITH_RISK_FILTERS;
                $filters['query'] = 'audit';
            }
        } elseif (str_contains($lower, 'inactive') || str_contains($lower, 'dormant')) {
            $filters['status'] = 'INACTIVE';
            $filters['last_active_before_days'] = 90;
        } elseif (str_contains($lower, 'failed login')) {
            $filters['failed_logins_last_days'] = 30;
        }

        if ($email !== null) {
            $filters['email'] = $email;
            if (in_array($intent, [
                AdminIntentCatalog::INTENT_RESET_USER_PASSWORD,
                AdminIntentCatalog::INTENT_EXPORT_AUDIT_FOR_USER,
            ], true)) {
                $limit = 1;
            }
        }

        return [
            'intent' => $intent,
            'filters' => $filters,
            'limit' => $limit,
            'reason' => trim($prompt),
            'user_id' => null,
            'fallback_used' => $fallbackUsed,
        ];
    }

    /**
     * @return array{intent: string, filters: array<string, mixed>, limit: int, reason: string, user_id: ?string, fallback_used: bool}
     */
    private function planFromPromptUncached(string $prompt): array
    {
        if ($this->groqApiKey === '') {
            return $this->fallbackParse($prompt, true);
        }

        $instruction = $this->buildInstruction($prompt);
        $this->logger->info('admin.ai.llm.request', [
            'provider' => 'groq',
            'model' => $this->model !== '' ? $this->model : 'llama3-70b-8192',
            'prompt' => $prompt,
            'instruction' => $instruction,
        ]);

        $lastError = null;
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('POST', self::GROQ_CHAT_COMPLETIONS_ENDPOINT, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->groqApiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $this->model !== '' ? $this->model : 'llama3-70b-8192',
                        'temperature' => 0.1,
                        'max_tokens' => 1024,
                        'response_format' => ['type' => 'json_object'],
                        'messages' => [
                            ['role' => 'system', 'content' => 'Return JSON only.'],
                            ['role' => 'user', 'content' => $instruction],
                        ],
                    ],
                ]);

                $payload = $response->toArray(false);
                $rawResponse = (string) ($payload['choices'][0]['message']['content'] ?? '');
                $this->logger->info('admin.ai.llm.raw_response', [
                    'provider' => 'groq',
                    'attempt' => $attempt,
                    'raw_response' => $rawResponse,
                ]);

                $json = $this->extractJson($rawResponse);
                if ($json === null) {
                    throw new \RuntimeException('No valid JSON object found in LLM response.');
                }

                $data = json_decode($json, true);
                if (!is_array($data)) {
                    throw new \RuntimeException('LLM response JSON parsing failed.');
                }

                $parsed = [
                    'intent' => (string) ($data['intent'] ?? ''),
                    'filters' => is_array($data['filters'] ?? null) ? $data['filters'] : [],
                    'limit' => (int) ($data['limit'] ?? 100),
                    'reason' => (string) ($data['reason'] ?? trim($prompt)),
                    'user_id' => isset($data['user_id']) ? (string) $data['user_id'] : null,
                    'fallback_used' => false,
                ];

                $this->logger->info('admin.ai.llm.parsed_intent', [
                    'provider' => 'groq',
                    'parsed_intent' => $parsed,
                ]);

                return $parsed;
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();
                $this->logger->warning('admin.ai.llm.error', [
                    'provider' => 'groq',
                    'attempt' => $attempt,
                    'error' => $exception->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    usleep($attempt * 200000);
                }
            }
        }

        $this->logger->error('admin.ai.llm.fallback_used', [
            'provider' => 'groq',
            'prompt' => $prompt,
            'last_error' => $lastError,
        ]);

        return $this->fallbackParse($prompt, true);
    }

    private function sanitizePrompt(string $prompt): string
    {
        $prompt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $prompt) ?? '';
        $prompt = trim($prompt);

        if (mb_strlen($prompt) > 2000) {
            $prompt = mb_substr($prompt, 0, 2000);
        }

        return $prompt;
    }

    private function containsSensitivePromptData(string $prompt): bool
    {
        if ($this->extractEmail($prompt) !== null) {
            return true;
        }

        return false;
    }

    private function extractEmail(string $text): ?string
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }
}
