<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HuggingFaceSummarizerClient
{
    private const DEFAULT_MODEL = 'facebook/bart-large-cnn';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly string $baseUri = 'https://router.huggingface.co/hf-inference/models'
    ) {
    }

    public function isEnabled(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /**
     * @return array{
     *   summary: string,
     *   input_length: int,
     *   truncated: bool,
     *   model: string,
     *   raw: array<mixed>
     * }
     */
    public function summarize(string $text, int $maxLength = 140, int $minLength = 40): array
    {
        if (!$this->isEnabled()) {
            throw new \InvalidArgumentException('HUGGINGFACE_API_KEY is empty. Set it in .env.local.');
        }

        if ($maxLength < 30 || $maxLength > 400) {
            throw new \InvalidArgumentException('max_length must be between 30 and 400.');
        }
        if ($minLength < 5 || $minLength >= $maxLength) {
            throw new \InvalidArgumentException('min_length must be >= 5 and < max_length.');
        }

        [$preparedText, $truncated] = $this->prepareInput($text);
        $uri = rtrim($this->baseUri, '/') . '/' . ltrim($this->model, '/');

        $response = $this->httpClient->request('POST', $uri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'inputs' => $preparedText,
                'parameters' => [
                    'max_length' => $maxLength,
                    'min_length' => $minLength,
                    'do_sample' => false,
                ],
                'options' => [
                    'wait_for_model' => true,
                ],
            ],
            'timeout' => 90,
        ]);

        $payload = $this->decodePayload($response);
        $summary = $this->extractSummary($payload);

        return [
            'summary' => $summary,
            'input_length' => mb_strlen($preparedText),
            'truncated' => $truncated,
            'model' => $this->model,
            'raw' => $payload,
        ];
    }

    /**
     * @return array{string,bool}
     */
    private function prepareInput(string $text): array
    {
        $normalized = trim($text);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if ($normalized === '') {
            throw new \InvalidArgumentException('Document text is empty.');
        }

        $maxChars = 12000;
        if (mb_strlen($normalized) <= $maxChars) {
            return [$normalized, false];
        }

        return [mb_substr($normalized, 0, $maxChars), true];
    }

    /**
     * @return array<mixed>
     */
    private function decodePayload(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);

        if (!is_array($payload)) {
            throw new \RuntimeException('Hugging Face response is invalid.');
        }

        if ($statusCode >= 400) {
            $message = $this->extractErrorMessage($payload);
            throw new \RuntimeException($message !== '' ? $message : ('Hugging Face request failed (HTTP ' . $statusCode . ').'));
        }

        if (isset($payload['error']) && is_string($payload['error']) && $payload['error'] !== '') {
            throw new \RuntimeException($payload['error']);
        }

        return $payload;
    }

    /**
     * @param array<mixed> $payload
     */
    private function extractSummary(array $payload): string
    {
        if (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['summary_text']) && is_string($payload[0]['summary_text'])) {
            $summary = trim($payload[0]['summary_text']);
            if ($summary !== '') {
                return $summary;
            }
        }

        if (isset($payload['summary_text']) && is_string($payload['summary_text'])) {
            $summary = trim($payload['summary_text']);
            if ($summary !== '') {
                return $summary;
            }
        }

        throw new \RuntimeException('Unable to extract summary from Hugging Face response.');
    }

    /**
     * @param array<mixed> $payload
     */
    private function extractErrorMessage(array $payload): string
    {
        $message = $payload['error'] ?? $payload['message'] ?? null;
        if (is_string($message)) {
            return trim($message);
        }

        return '';
    }
}
