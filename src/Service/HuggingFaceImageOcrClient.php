<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HuggingFaceImageOcrClient
{
    private const DEFAULT_MODEL = 'microsoft/trocr-base-printed';

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

    public function extractTextFromImage(string $absolutePath): string
    {
        if (!$this->isEnabled()) {
            throw new \InvalidArgumentException('HUGGINGFACE_API_KEY is empty. Set it in .env.local.');
        }

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new \RuntimeException('Image file is not readable for OCR.');
        }

        $binary = file_get_contents($absolutePath);
        if (!is_string($binary) || $binary === '') {
            throw new \RuntimeException('Unable to read image bytes for OCR.');
        }

        $uri = rtrim($this->baseUri, '/') . '/' . ltrim($this->model, '/');
        $response = $this->httpClient->request('POST', $uri, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $binary,
            'timeout' => 90,
        ]);

        $payload = $this->decodePayload($response);
        $text = $this->extractText($payload);

        if ($text === '') {
            throw new \RuntimeException('OCR returned empty text.');
        }

        return $text;
    }

    /**
     * @return array<mixed>
     */
    private function decodePayload(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        if (!is_array($payload)) {
            throw new \RuntimeException('Hugging Face OCR response is invalid.');
        }

        if ($statusCode >= 400) {
            $message = $this->extractErrorMessage($payload);
            throw new \RuntimeException($message !== '' ? $message : ('Hugging Face OCR request failed (HTTP ' . $statusCode . ').'));
        }

        $errorMessage = $this->extractErrorMessage($payload);
        if ($errorMessage !== '') {
            throw new \RuntimeException($errorMessage);
        }

        return $payload;
    }

    /**
     * @param array<mixed> $payload
     */
    private function extractText(array $payload): string
    {
        if (isset($payload[0]) && is_array($payload[0])) {
            $first = $payload[0];
            if (isset($first['generated_text']) && is_string($first['generated_text'])) {
                return trim($first['generated_text']);
            }
            if (isset($first['text']) && is_string($first['text'])) {
                return trim($first['text']);
            }
        }

        if (isset($payload['generated_text']) && is_string($payload['generated_text'])) {
            return trim($payload['generated_text']);
        }
        if (isset($payload['text']) && is_string($payload['text'])) {
            return trim($payload['text']);
        }

        return '';
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

        if (isset($payload[0]) && is_array($payload[0])) {
            $message = $payload[0]['error'] ?? $payload[0]['message'] ?? null;
            if (is_string($message)) {
                return trim($message);
            }
        }

        return '';
    }
}

