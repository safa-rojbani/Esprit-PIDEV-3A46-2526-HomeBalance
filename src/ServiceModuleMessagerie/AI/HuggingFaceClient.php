<?php

namespace App\ServiceModuleMessagerie\AI;

use App\Exception\HuggingFaceException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HuggingFaceClient
{
    private const BASE_URL = 'https://api-inference.huggingface.co/models/';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 20000;

    private readonly HttpClientInterface $httpClient;
    private readonly string $apiToken;

    public function __construct(
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
        $this->apiToken = $_ENV['HUGGINGFACE_API_TOKEN'] ?? $_SERVER['HUGGINGFACE_API_TOKEN'] ?? '';
        
        if (empty($this->apiToken)) {
            throw new \InvalidArgumentException('HUGGINGFACE_API_TOKEN environment variable is not set');
        }
    }

    /**
     * Query a Hugging Face model with the given payload.
     *
     * @param string $model The model name (e.g., 'facebook/blenderbot-400M-distill')
     * @param array $payload The payload to send to the model
     * @return array The response from the model
     * @throws HuggingFaceException On failure after retries
     */
    public function query(string $model, array $payload): array
    {
        $url = self::BASE_URL . $model;
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $payload,
                ]);

                $statusCode = $response->getStatusCode();

                // Handle 503 - Model loading, retry after delay
                if ($statusCode === 503) {
                    $lastException = new HuggingFaceException(
                        'Model is loading, retrying...',
                        ['model' => $model, 'attempt' => $attempt],
                        $statusCode
                    );
                    
                    if ($attempt < self::MAX_RETRIES) {
                        usleep(self::RETRY_DELAY_MS * 1000); // Convert to microseconds
                        continue;
                    }
                }

                $content = $response->toArray();

                if ($statusCode >= 200 && $statusCode < 300) {
                    return $content;
                }

                // Handle other error responses
                $errorMessage = $content['error'] ?? 'Unknown error';
                throw new HuggingFaceException(
                    $errorMessage,
                    ['model' => $model, 'statusCode' => $statusCode, 'response' => $content],
                    $statusCode
                );

            } catch (ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface|DecodingExceptionInterface $e) {
                $lastException = new HuggingFaceException(
                    'HTTP error: ' . $e->getMessage(),
                    ['model' => $model, 'attempt' => $attempt],
                    $e->getCode(),
                    $e
                );

                if ($attempt < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000);
                    continue;
                }
            } catch (\Throwable $e) {
                throw new HuggingFaceException(
                    'Failed to query Hugging Face API: ' . $e->getMessage(),
                    ['model' => $model],
                    $e->getCode(),
                    $e
                );
            }
        }

        throw $lastException ?? new HuggingFaceException(
            'Failed to query model after ' . self::MAX_RETRIES . ' attempts',
            ['model' => $model]
        );
    }
}
