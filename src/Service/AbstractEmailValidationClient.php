<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AbstractEmailValidationClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $baseUri = 'https://emailvalidation.abstractapi.com/v1/',
        private readonly float $timeoutSeconds = 8.0
    ) {
    }

    public function isEnabled(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /**
     * @return array{
     *   is_valid: bool,
     *   reason: string,
     *   suggestion: string|null,
     *   details: array{
     *     deliverability: string|null,
     *     is_valid_format: bool|null,
     *     is_disposable: bool|null,
     *     is_mx_valid: bool|null,
     *     is_smtp_valid: bool|null
     *   },
     *   raw: array<string, mixed>
     * }
     */
    public function validate(string $email): array
    {
        if (!$this->isEnabled()) {
            return [
                'is_valid' => true,
                'reason' => 'validation_disabled',
                'suggestion' => null,
                'details' => [
                    'deliverability' => null,
                    'is_valid_format' => null,
                    'is_disposable' => null,
                    'is_mx_valid' => null,
                    'is_smtp_valid' => null,
                ],
                'raw' => [],
            ];
        }

        $response = $this->httpClient->request('GET', rtrim($this->baseUri, '/') . '/', [
            'query' => [
                'api_key' => $this->apiKey,
                'email' => $email,
            ],
            'timeout' => $this->timeoutSeconds,
            'max_duration' => $this->timeoutSeconds + 1,
        ]);

        $payload = $this->decode($response);

        $deliverability = $this->readDeliverability($payload);
        $isValidFormat = $this->readBooleanFlag($payload, ['is_valid_format', 'value'])
            ?? $this->readBooleanFlag($payload, ['email_deliverability', 'is_format_valid']);
        $isDisposable = $this->readBooleanFlag($payload, ['is_disposable_email', 'value'])
            ?? $this->readBooleanFlag($payload, ['email_quality', 'is_disposable']);
        $isMxValid = $this->readBooleanFlag($payload, ['is_mx_found', 'value'])
            ?? $this->readBooleanFlag($payload, ['email_deliverability', 'is_mx_valid']);
        $isSmtpValid = $this->readBooleanFlag($payload, ['is_smtp_valid', 'value'])
            ?? $this->readBooleanFlag($payload, ['email_deliverability', 'is_smtp_valid']);
        $suggestion = $this->readSuggestion($payload);

        $isDeliverable = $deliverability === 'DELIVERABLE';
        $isUndeliverable = $deliverability === 'UNDELIVERABLE';

        $isValid = $isValidFormat !== false
            && $isDisposable !== true
            && $isUndeliverable !== true
            && $isMxValid !== false
            && $isSmtpValid !== false;

        $reason = 'valid';
        if ($isValidFormat === false) {
            $reason = 'invalid_format';
        } elseif ($isDisposable === true) {
            $reason = 'disposable_email';
        } elseif ($isUndeliverable) {
            $reason = 'undeliverable';
        } elseif ($isMxValid === false || $isSmtpValid === false) {
            $reason = 'mx_smtp_failed';
        } elseif (!$isDeliverable && $deliverability !== null) {
            $reason = 'unknown_deliverability';
        }

        return [
            'is_valid' => $isValid,
            'reason' => $reason,
            'suggestion' => $suggestion,
            'details' => [
                'deliverability' => $deliverability,
                'is_valid_format' => $isValidFormat,
                'is_disposable' => $isDisposable,
                'is_mx_valid' => $isMxValid,
                'is_smtp_valid' => $isSmtpValid,
            ],
            'raw' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($statusCode >= 400) {
            $message = (string) ($payload['error']['message'] ?? $payload['message'] ?? 'Abstract API request failed.');
            throw new \RuntimeException($message);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readDeliverability(array $payload): ?string
    {
        $value = $payload['deliverability'] ?? $payload['email_deliverability']['status'] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $path
     */
    private function readBooleanFlag(array $payload, array $path): ?bool
    {
        $value = $this->readPath($payload, $path);

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['true', '1', 'yes'], true)) {
            return true;
        }
        if (in_array($normalized, ['false', '0', 'no'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readSuggestion(array $payload): ?string
    {
        $autocorrect = $payload['autocorrect'] ?? null;
        if (!is_string($autocorrect)) {
            return null;
        }

        $autocorrect = trim($autocorrect);

        return $autocorrect !== '' ? $autocorrect : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $path
     */
    private function readPath(array $payload, array $path): mixed
    {
        $value = $payload;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

