<?php

namespace App\Service\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeeklyInsightsAiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:AI_TEXT_PROVIDER)%')]
        private readonly string $configuredProvider,
        #[Autowire('%env(string:OPENAI_API_KEY)%')]
        private readonly string $openAiApiKey,
        #[Autowire('%env(string:OPENAI_TEXT_MODEL)%')]
        private readonly string $openAiModel,
        #[Autowire('%env(string:GEMINI_API_KEY)%')]
        private readonly string $geminiApiKey,
        #[Autowire('%env(string:GEMINI_TEXT_MODEL)%')]
        private readonly string $geminiModel,
    ) {
    }

    /**
     * @param array<string, mixed> $dataset
     * @return array{
     *   status: string,
     *   provider: string,
     *   model: ?string,
     *   summary: array<string, mixed>,
     *   rawResponse: ?string,
     *   error: ?string
     * }
     */
    public function generate(array $dataset): array
    {
        $provider = mb_strtolower(trim($this->configuredProvider));

        return match ($provider) {
            'gemini', 'google_gemini' => $this->generateWithGemini($dataset),
            'openai' => $this->generateWithOpenAi($dataset),
            default => trim($this->geminiApiKey) !== ''
                ? $this->generateWithGemini($dataset)
                : $this->generateWithOpenAi($dataset),
        };
    }

    /**
     * @param array<string, mixed> $dataset
     * @return array{
     *   status: string,
     *   provider: string,
     *   model: ?string,
     *   summary: array<string, mixed>,
     *   rawResponse: ?string,
     *   error: ?string
     * }
     */
    private function generateWithOpenAi(array $dataset): array
    {
        if (trim($this->openAiApiKey) === '') {
            return $this->fallbackResult($dataset, 'OPENAI_API_KEY missing.');
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->openAiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->openAiModel,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an analytics copilot for family task management. Return strict JSON only.'],
                        ['role' => 'user', 'content' => $this->buildPrompt($dataset)],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 900,
                ],
                'timeout' => 45,
            ]);
        } catch (TransportExceptionInterface $e) {
            return $this->fallbackResult($dataset, 'Transport error: '.$e->getMessage());
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        $rawResponse = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($statusCode >= 400) {
            return $this->fallbackResult($dataset, sprintf('OpenAI API error HTTP %d.', $statusCode), $rawResponse);
        }

        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            return $this->fallbackResult($dataset, 'Empty response content.', $rawResponse);
        }

        $decoded = json_decode($this->stripCodeFence($content), true);
        if (!is_array($decoded)) {
            return $this->fallbackResult($dataset, 'Invalid JSON from model.', $rawResponse);
        }

        return [
            'status' => 'SUCCESS',
            'provider' => 'openai',
            'model' => $this->openAiModel,
            'summary' => $this->normalizeAiSummary($decoded, $dataset),
            'rawResponse' => $rawResponse,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $dataset
     * @return array{
     *   status: string,
     *   provider: string,
     *   model: ?string,
     *   summary: array<string, mixed>,
     *   rawResponse: ?string,
     *   error: ?string
     * }
     */
    private function generateWithGemini(array $dataset): array
    {
        if (trim($this->geminiApiKey) === '') {
            return $this->fallbackResult($dataset, 'GEMINI_API_KEY missing.');
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            urlencode($this->geminiModel),
            urlencode($this->geminiApiKey)
        );

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [
                            ['text' => $this->buildPrompt($dataset)],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 2200,
                        'responseMimeType' => 'application/json',
                        'thinkingConfig' => [
                            'thinkingBudget' => 0,
                        ],
                    ],
                ],
                'timeout' => 45,
            ]);
        } catch (TransportExceptionInterface $e) {
            return $this->fallbackResult($dataset, 'Transport error: '.$e->getMessage());
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        $rawResponse = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($statusCode >= 400) {
            return $this->fallbackResult($dataset, sprintf('Gemini API error HTTP %d.', $statusCode), $rawResponse);
        }

        $content = $this->extractTextContent($payload);
        if ($content === '') {
            return $this->fallbackResult($dataset, 'Empty response content.', $rawResponse);
        }

        $decoded = json_decode($this->stripCodeFence($content), true);
        if (!is_array($decoded)) {
            return $this->fallbackResult($dataset, 'Invalid JSON from model.', $rawResponse);
        }

        return [
            'status' => 'SUCCESS',
            'provider' => 'gemini',
            'model' => $this->geminiModel,
            'summary' => $this->normalizeAiSummary($decoded, $dataset),
            'rawResponse' => $rawResponse,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $dataset
     * @return array{
     *   status: string,
     *   provider: string,
     *   model: ?string,
     *   summary: array<string, mixed>,
     *   rawResponse: ?string,
     *   error: ?string
     * }
     */
    private function fallbackResult(array $dataset, string $error, ?string $rawResponse = null): array
    {
        return [
            'status' => 'FALLBACK',
            'provider' => 'local',
            'model' => null,
            'summary' => $this->fallbackSummary($dataset),
            'rawResponse' => $rawResponse,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $dataset
     */
    private function buildPrompt(array $dataset): string
    {
        $payload = [
            'period' => $dataset['period'] ?? [],
            'family' => $dataset['family'] ?? [],
            'familyTotals' => $dataset['familyTotals'] ?? [],
            'mostImprovedCandidate' => $dataset['mostImprovedCandidate'] ?? [],
            'blockingTasks' => $dataset['blockingTasks'] ?? [],
            'engagementSeed' => $dataset['engagementSeed'] ?? [],
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($jsonPayload)) {
            $jsonPayload = '{}';
        }

        return <<<PROMPT
Analyze this family-week dataset and return ONLY valid JSON with this schema:
{
  "mostImproved": {
    "memberId": "string|null",
    "memberName": "string",
    "insight": "short french sentence",
    "deltaPoints": integer
  },
  "familyMomentum": "short french sentence",
  "blockingTasks": [
    {
      "taskTitle": "string",
      "severity": "low|medium|high",
      "why": "short french sentence"
    }
  ],
  "recommendations": ["short french sentence", "..."],
  "engagement": [
    {
      "memberId": "string",
      "memberName": "string",
      "status": "stable|watch|low",
      "signal": "short french sentence",
      "challenges": ["challenge 1", "challenge 2", "challenge 3"]
    }
  ]
}
Rules:
- French language for all narrative text.
- Keep recommendations practical and specific.
- Keep max 4 recommendations, max 3 blockingTasks, max 3 challenges per child.
- Do not invent members not present in input.

INPUT:
$jsonPayload
PROMPT;
    }

    /**
     * @param array<string, mixed> $decoded
     * @param array<string, mixed> $dataset
     * @return array<string, mixed>
     */
    private function normalizeAiSummary(array $decoded, array $dataset): array
    {
        $fallback = $this->fallbackSummary($dataset);
        $membersById = [];
        foreach (($dataset['memberStats'] ?? []) as $member) {
            if (!is_array($member)) {
                continue;
            }
            $id = (string) ($member['memberId'] ?? '');
            if ($id === '') {
                continue;
            }
            $membersById[$id] = (string) ($member['memberName'] ?? $id);
        }

        $mostImproved = $decoded['mostImproved'] ?? [];
        if (!is_array($mostImproved)) {
            $mostImproved = [];
        }

        $memberId = (string) ($mostImproved['memberId'] ?? '');
        if ($memberId !== '' && isset($membersById[$memberId])) {
            $mostImprovedName = $membersById[$memberId];
        } else {
            $memberId = (string) ($fallback['mostImproved']['memberId'] ?? '');
            $mostImprovedName = (string) ($fallback['mostImproved']['memberName'] ?? 'Aucun membre');
        }

        $blockingTasks = $decoded['blockingTasks'] ?? [];
        if (!is_array($blockingTasks)) {
            $blockingTasks = [];
        }
        $blockingTasks = array_values(array_filter(array_map(static function ($row): ?array {
            if (!is_array($row)) {
                return null;
            }

            $title = trim((string) ($row['taskTitle'] ?? ''));
            if ($title === '') {
                return null;
            }

            $severity = strtolower(trim((string) ($row['severity'] ?? 'medium')));
            if (!in_array($severity, ['low', 'medium', 'high'], true)) {
                $severity = 'medium';
            }

            return [
                'taskTitle' => $title,
                'severity' => $severity,
                'why' => trim((string) ($row['why'] ?? 'Blocage detecte.')),
            ];
        }, $blockingTasks)));
        if ($blockingTasks === []) {
            $blockingTasks = $fallback['blockingTasks'];
        }
        $blockingTasks = array_slice($blockingTasks, 0, 3);

        $recommendations = $decoded['recommendations'] ?? [];
        if (!is_array($recommendations)) {
            $recommendations = [];
        }
        $recommendations = array_values(array_filter(array_map(static fn ($r): string => trim((string) $r), $recommendations), static fn ($r): bool => $r !== ''));
        if ($recommendations === []) {
            $recommendations = $fallback['recommendations'];
        }
        $recommendations = array_slice($recommendations, 0, 4);

        $engagement = $decoded['engagement'] ?? [];
        if (!is_array($engagement)) {
            $engagement = [];
        }
        $engagementById = [];
        foreach ($engagement as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = (string) ($item['memberId'] ?? '');
            if ($id === '' || !isset($membersById[$id])) {
                continue;
            }
            $status = strtolower(trim((string) ($item['status'] ?? 'watch')));
            if (!in_array($status, ['stable', 'watch', 'low'], true)) {
                $status = 'watch';
            }
            $challengesRaw = $item['challenges'] ?? [];
            if (!is_array($challengesRaw)) {
                $challengesRaw = [];
            }
            $challenges = array_values(array_filter(array_map(static fn ($c): string => trim((string) $c), $challengesRaw), static fn ($c): bool => $c !== ''));
            $engagementById[$id] = [
                'memberId' => $id,
                'memberName' => $membersById[$id],
                'status' => $status,
                'signal' => trim((string) ($item['signal'] ?? 'Variation d engagement detectee.')),
                'challenges' => array_slice($challenges, 0, 3),
            ];
        }

        $finalEngagement = [];
        foreach (($fallback['engagement'] ?? []) as $seed) {
            if (!is_array($seed)) {
                continue;
            }
            $id = (string) ($seed['memberId'] ?? '');
            if ($id === '') {
                continue;
            }
            if (isset($engagementById[$id])) {
                $merged = $engagementById[$id];
                if ($merged['challenges'] === []) {
                    $merged['challenges'] = (array) ($seed['challenges'] ?? []);
                }
                $finalEngagement[] = $merged;
                continue;
            }
            $finalEngagement[] = $seed;
        }

        return [
            'mostImproved' => [
                'memberId' => $memberId !== '' ? $memberId : null,
                'memberName' => $mostImprovedName,
                'insight' => trim((string) ($mostImproved['insight'] ?? $fallback['mostImproved']['insight'] ?? '')),
                'deltaPoints' => (int) ($mostImproved['deltaPoints'] ?? $fallback['mostImproved']['deltaPoints'] ?? 0),
            ],
            'familyMomentum' => trim((string) ($decoded['familyMomentum'] ?? $fallback['familyMomentum'] ?? '')),
            'blockingTasks' => $blockingTasks,
            'recommendations' => $recommendations,
            'engagement' => $finalEngagement,
        ];
    }

    /**
     * @param array<string, mixed> $dataset
     * @return array<string, mixed>
     */
    private function fallbackSummary(array $dataset): array
    {
        $mostImproved = $dataset['mostImprovedCandidate'] ?? [];
        if (!is_array($mostImproved)) {
            $mostImproved = [];
        }

        $totals = $dataset['familyTotals'] ?? [];
        if (!is_array($totals)) {
            $totals = [];
        }
        $deltaFamily = (int) ($totals['pointsCurrent'] ?? 0) - (int) ($totals['pointsPrevious'] ?? 0);
        $familyMomentum = $deltaFamily >= 0
            ? sprintf('La famille progresse de %+d points par rapport a la semaine precedente.', $deltaFamily)
            : sprintf('La famille recule de %d points: un plan de rattrapage est conseille.', abs($deltaFamily));

        $blockingTasks = [];
        foreach (array_slice((array) ($dataset['blockingTasks'] ?? []), 0, 3) as $task) {
            if (!is_array($task)) {
                continue;
            }
            $blockingTasks[] = [
                'taskTitle' => (string) ($task['taskTitle'] ?? 'Tache'),
                'severity' => (string) ($task['severity'] ?? 'medium'),
                'why' => (string) ($task['reasonSeed'] ?? 'Blocage detecte sur cette tache.'),
            ];
        }

        $recommendations = [];
        if ((int) ($totals['lateCurrent'] ?? 0) > 0) {
            $recommendations[] = 'Revoir les deadlines des taches avec retards repetes.';
        }
        if ((int) ($totals['refusedCurrent'] ?? 0) > 0) {
            $recommendations[] = 'Clarifier les criteres de validation photo pour reduire les refus.';
        }
        if ($deltaFamily < 0) {
            $recommendations[] = 'Lancer un mini challenge familial de rattrapage (+30 points en 5 jours).';
        }
        if ($recommendations === []) {
            $recommendations[] = 'Maintenir le rythme actuel et augmenter progressivement la difficulte.';
        }

        $engagement = [];
        foreach ((array) ($dataset['engagementSeed'] ?? []) as $seed) {
            if (!is_array($seed)) {
                continue;
            }
            $engagement[] = [
                'memberId' => (string) ($seed['memberId'] ?? ''),
                'memberName' => (string) ($seed['memberName'] ?? 'Membre'),
                'status' => (string) ($seed['status'] ?? 'watch'),
                'signal' => (string) ($seed['signalSeed'] ?? 'Variation d engagement detectee.'),
                'challenges' => (array) ($seed['challenges'] ?? []),
            ];
        }

        return [
            'mostImproved' => [
                'memberId' => $mostImproved['memberId'] ?? null,
                'memberName' => (string) ($mostImproved['memberName'] ?? 'Aucun membre'),
                'insight' => (string) ($mostImproved['insightSeed'] ?? 'Pas assez de donnees.'),
                'deltaPoints' => (int) ($mostImproved['deltaPoints'] ?? 0),
            ],
            'familyMomentum' => $familyMomentum,
            'blockingTasks' => $blockingTasks,
            'recommendations' => array_slice($recommendations, 0, 4),
            'engagement' => $engagement,
        ];
    }

    private function stripCodeFence(string $content): string
    {
        $trimmed = trim($content);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractTextContent(array $payload): string
    {
        $candidates = $payload['candidates'] ?? null;
        if (!is_array($candidates) || $candidates === []) {
            return '';
        }

        $parts = $candidates[0]['content']['parts'] ?? null;
        if (!is_array($parts)) {
            return '';
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }
            $text = $part['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                $chunks[] = $text;
            }
        }

        return trim(implode("\n", $chunks));
    }
}
