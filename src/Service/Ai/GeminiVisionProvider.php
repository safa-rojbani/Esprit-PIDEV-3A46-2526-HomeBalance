<?php

namespace App\Service\Ai;

use App\Enum\AiEvaluationDecision;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiVisionProvider implements VisionProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:GEMINI_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(string:GEMINI_VISION_MODEL)%')]
        private readonly string $model,
    ) {
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    public function analyzeRoomImage(string $absoluteImagePath): VisionAnalysisResult
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('GEMINI_API_KEY is missing.');
        }

        if (!is_file($absoluteImagePath)) {
            throw new \RuntimeException('Image file not found for Gemini analysis.');
        }

        $mimeType = (string) (mime_content_type($absoluteImagePath) ?: 'image/jpeg');
        $rawImage = file_get_contents($absoluteImagePath);
        if ($rawImage === false) {
            throw new \RuntimeException('Unable to read image content for Gemini analysis.');
        }

        $prompt = <<<'PROMPT'
Analyze this room photo and rate tidiness.
Return ONLY valid JSON with this exact schema:
{
  "tidy_score": integer between 0 and 100,
  "confidence": number between 0 and 1,
  "decision": "PASS" | "FAIL" | "REVIEW",
  "reason_short": short sentence max 140 chars,
  "detected_signals": string[]
}
Rules:
- PASS if clearly clean and organized.
- FAIL if clearly messy/dirty.
- REVIEW if uncertain or mixed.
PROMPT;

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            urlencode($this->model),
            urlencode($this->apiKey)
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
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => base64_encode($rawImage),
                                ],
                            ],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 500,
                        'responseMimeType' => 'application/json',
                    ],
                ],
                'timeout' => 45,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Gemini request failed: '.$e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        if ($statusCode >= 400) {
            $errorBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
            throw new \RuntimeException(sprintf('Gemini API error (HTTP %d): %s', $statusCode, (string) $errorBody));
        }

        $content = $this->extractTextContent($payload);
        if ($content === '') {
            throw new \RuntimeException('Gemini returned an empty content payload.');
        }

        $parsed = $this->decodeJsonObject($content);
        if (!is_array($parsed)) {
            $excerpt = mb_substr(preg_replace('/\s+/', ' ', trim($content)) ?? '', 0, 260);
            throw new \RuntimeException(sprintf('Gemini response is not valid JSON. Raw excerpt: %s', $excerpt));
        }

        $score = VisionAnalysisResult::clampScore((int) ($parsed['tidy_score'] ?? 0));
        $confidence = VisionAnalysisResult::clampConfidence((float) ($parsed['confidence'] ?? 0.0));
        $decisionRaw = strtoupper((string) ($parsed['decision'] ?? 'REVIEW'));
        $decision = match ($decisionRaw) {
            AiEvaluationDecision::PASS->value => AiEvaluationDecision::PASS,
            AiEvaluationDecision::FAIL->value => AiEvaluationDecision::FAIL,
            default => AiEvaluationDecision::REVIEW,
        };
        $reason = (string) ($parsed['reason_short'] ?? 'Gemini did not provide a reason.');

        return VisionAnalysisResult::fromValues(
            $score,
            $confidence,
            $decision,
            $reason,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
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
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $content): ?array
    {
        $normalized = $this->stripCodeFence($content);

        $decoded = json_decode($normalized, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($normalized, '{');
        $end = strrpos($normalized, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = substr($normalized, $start, $end - $start + 1);
        if (trim($candidate) === '') {
            return null;
        }

        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return null;
    }
}
