<?php

namespace App\Service\External;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FacePlusPlusClient
{
    private const SUBJECT_CREATE_ENDPOINT = 'https://api.luxand.cloud/subject/v2';
    private const VERIFY_ENDPOINT_TEMPLATE = 'https://api.luxand.cloud/photo/verify/%s';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {
    }

    public function enrollReference(UploadedFile $image): string
    {
        $this->assertConfigured();
        $subjectName = 'admin_ref_' . bin2hex(random_bytes(8));

        $response = $this->httpClient->request('POST', self::SUBJECT_CREATE_ENDPOINT, [
            'headers' => [
                'token' => $this->apiKey,
            ],
            'body' => [
                'name' => $subjectName,
                'photo' => fopen($image->getPathname(), 'rb'),
            ],
        ]);

        $payload = $response->toArray(false);
        if (isset($payload['error'])) {
            throw new \RuntimeException('Luxand enrollment failed: ' . (string) $payload['error']);
        }

        $subjectId = $this->extractSubjectReference($payload);
        if ($subjectId === '') {
            // Some Luxand responses may omit explicit id on successful subject creation;
            // in that case we can use the unique subject name as reference.
            $subjectId = $subjectName;
        }

        return $subjectId;
    }

    /**
     * @return array{confidence: float, thresholds: array<string, float>, match: bool}
     */
    public function verifyReference(string $subjectId, UploadedFile $image): array
    {
        $this->assertConfigured();

        $response = $this->httpClient->request('POST', sprintf(self::VERIFY_ENDPOINT_TEMPLATE, rawurlencode($subjectId)), [
            'headers' => [
                'token' => $this->apiKey,
            ],
            'body' => [
                'photo' => fopen($image->getPathname(), 'rb'),
            ],
        ]);

        $payload = $response->toArray(false);
        if (isset($payload['error'])) {
            throw new \RuntimeException('Luxand verification failed: ' . (string) $payload['error']);
        }

        $rawConfidence = $payload['confidence'] ?? $payload['score'] ?? $payload['probability'] ?? 0.0;
        $confidence = (float) $rawConfidence;
        if ($confidence > 0.0 && $confidence <= 1.0) {
            $confidence *= 100.0;
        }

        $match = false;
        if (isset($payload['status']) && is_string($payload['status'])) {
            $status = strtoupper($payload['status']);
            $match = in_array($status, ['SUCCESS', 'MATCH', 'VERIFIED'], true);
        }
        if (isset($payload['isIdentical'])) {
            $match = (bool) $payload['isIdentical'];
        }
        if (isset($payload['match'])) {
            $match = (bool) $payload['match'];
        }

        return [
            'confidence' => $confidence,
            'thresholds' => [],
            'match' => $match,
        ];
    }

    private function assertConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Luxand API key is not configured.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractSubjectReference(array $payload): string
    {
        $candidates = ['id', 'uuid', 'subject_id', 'subjectId', 'person_id', 'personId', 'name', 'subject', 'person'];
        foreach ($candidates as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_array($value)) {
                foreach (['id', 'uuid', 'name', 'subject_id'] as $nestedKey) {
                    $nestedValue = $value[$nestedKey] ?? null;
                    if (is_string($nestedValue) && trim($nestedValue) !== '') {
                        return trim($nestedValue);
                    }
                }
            }
        }

        return '';
    }
}
