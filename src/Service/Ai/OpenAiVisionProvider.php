<?php

namespace App\Service\Ai;

use App\Enum\AiEvaluationDecision;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiVisionProvider implements VisionProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:OPENAI_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(string:OPENAI_VISION_MODEL)%')]
        private readonly string $model,
    ) {
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    public function analyzeRoomImage(string $absoluteImagePath): VisionAnalysisResult
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('OPENAI_API_KEY is missing.');
        }

        if (!is_file($absoluteImagePath)) {
            throw new \RuntimeException('Image file not found for OpenAI analysis.');
        }

        $mimeType = (string) (mime_content_type($absoluteImagePath) ?: 'image/jpeg');
        $rawImage = file_get_contents($absoluteImagePath);
        if ($rawImage === false) {
            throw new \RuntimeException('Unable to read image content for OpenAI analysis.');
        }

        $dataUri = sprintf('data:%s;base64,%s', $mimeType, base64_encode($rawImage));
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

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a strict image evaluator for household task proof.'],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUri, 'detail' => 'high']],
                        ]],
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 400,
                ],
                'timeout' => 45,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('OpenAI request failed: '.$e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        if ($statusCode >= 400) {
            $errorBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
            throw new \RuntimeException(sprintf('OpenAI API error (HTTP %d): %s', $statusCode, (string) $errorBody));
        }

        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('OpenAI returned an empty content payload.');
        }

        $normalizedContent = $this->stripCodeFence($content);
        $parsed = json_decode($normalizedContent, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('OpenAI response is not valid JSON.');
        }

        $score = VisionAnalysisResult::clampScore((int) ($parsed['tidy_score'] ?? 0));
        $confidence = VisionAnalysisResult::clampConfidence((float) ($parsed['confidence'] ?? 0.0));
        $decisionRaw = strtoupper((string) ($parsed['decision'] ?? 'REVIEW'));
        $decision = match ($decisionRaw) {
            AiEvaluationDecision::PASS->value => AiEvaluationDecision::PASS,
            AiEvaluationDecision::FAIL->value => AiEvaluationDecision::FAIL,
            default => AiEvaluationDecision::REVIEW,
        };
        $reason = (string) ($parsed['reason_short'] ?? 'OpenAI did not provide a reason.');

        return VisionAnalysisResult::fromValues(
            $score,
            $confidence,
            $decision,
            $reason,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
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
}

