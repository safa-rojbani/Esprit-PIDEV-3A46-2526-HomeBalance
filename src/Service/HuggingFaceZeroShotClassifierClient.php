<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HuggingFaceZeroShotClassifierClient
{
    private const DEFAULT_MODEL = 'facebook/bart-large-mnli';

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
     * @param list<string> $labels
     * @return array{
     *   labels: list<array{label:string,score:float}>,
     *   top_label: string,
     *   top_score: float,
     *   model: string,
     *   input_length: int,
     *   input_truncated: bool
     * }
     */
    public function classify(string $text, array $labels, bool $multiLabel = false): array
    {
        if (!$this->isEnabled()) {
            throw new \InvalidArgumentException('HUGGINGFACE_API_KEY is empty. Set it in .env.local.');
        }

        $candidateLabels = $this->normalizeLabels($labels);
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
                    'candidate_labels' => $candidateLabels,
                    'multi_label' => $multiLabel,
                ],
                'options' => [
                    'wait_for_model' => true,
                ],
            ],
            'timeout' => 90,
        ]);

        $payload = $this->decodePayload($response);
        $parsedLabels = $this->extractLabels($payload);
        if ($parsedLabels === []) {
            throw new \RuntimeException(
                'No classification labels returned by Hugging Face. Raw payload: ' . $this->truncateJson($payload)
            );
        }

        $top = $parsedLabels[0];

        return [
            'labels' => $parsedLabels,
            'top_label' => $top['label'],
            'top_score' => $top['score'],
            'model' => $this->model,
            'input_length' => mb_strlen($preparedText),
            'input_truncated' => $truncated,
        ];
    }

    /**
     * @param list<string> $labels
     * @return list<string>
     */
    private function normalizeLabels(array $labels): array
    {
        $normalized = [];
        foreach ($labels as $label) {
            if (!is_string($label)) {
                continue;
            }

            $clean = trim($label);
            if ($clean === '') {
                continue;
            }

            $normalized[$clean] = true;
        }

        $result = array_keys($normalized);
        if (\count($result) < 2) {
            throw new \InvalidArgumentException('At least 2 labels are required for zero-shot classification.');
        }
        if (\count($result) > 20) {
            throw new \InvalidArgumentException('Maximum 20 labels are allowed.');
        }

        return $result;
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

        if ($statusCode >= 400) {
            $message = $this->extractErrorMessage($payload);
            throw new \RuntimeException($message !== '' ? $message : ('Hugging Face request failed (HTTP ' . $statusCode . ').'));
        }

        $errorMessage = $this->extractErrorMessage($payload);
        if ($errorMessage !== '') {
            throw new \RuntimeException($errorMessage);
        }

        return $payload;
    }

    /**
     * @param array<mixed> $payload
     * @return list<array{label:string,score:float}>
     */
    private function extractLabels(array $payload): array
    {
        $labels = $this->extractFromLabelAndScoreArrays($payload);
        if ($labels !== []) {
            return $labels;
        }

        // Some providers return an envelope list, e.g. [{labels:[...],scores:[...]}]
        if (isset($payload[0]) && is_array($payload[0])) {
            $labels = $this->extractFromLabelAndScoreArrays($payload[0]);
            if ($labels !== []) {
                return $labels;
            }
        }

        // Some providers return list form: [{label:"...",score:0.9}, ...]
        $labels = $this->extractFromLabelScoreRows($payload);
        if ($labels !== []) {
            return $labels;
        }

        if (isset($payload[0]) && is_array($payload[0])) {
            $labels = $this->extractFromLabelScoreRows($payload[0]);
            if ($labels !== []) {
                return $labels;
            }
        }

        // Fallback common envelopes.
        foreach (['output', 'result', 'data'] as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            $labels = $this->extractFromLabelAndScoreArrays($payload[$key]);
            if ($labels === []) {
                $labels = $this->extractFromLabelScoreRows($payload[$key]);
            }
            if ($labels !== []) {
                return $labels;
            }
        }

        return [];
    }

    /**
     * @param array<mixed> $payload
     * @return list<array{label:string,score:float}>
     */
    private function extractFromLabelAndScoreArrays(array $payload): array
    {
        if (!isset($payload['labels'], $payload['scores']) || !is_array($payload['labels']) || !is_array($payload['scores'])) {
            return [];
        }

        $labels = [];
        foreach ($payload['labels'] as $index => $label) {
            if (!is_string($label) || trim($label) === '') {
                continue;
            }

            $score = $payload['scores'][$index] ?? null;
            if (!is_numeric($score)) {
                continue;
            }

            $labels[] = [
                'label' => trim($label),
                'score' => (float) $score,
            ];
        }

        if ($labels === []) {
            return [];
        }

        usort($labels, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $labels;
    }

    /**
     * @param array<mixed> $payload
     * @return list<array{label:string,score:float}>
     */
    private function extractFromLabelScoreRows(array $payload): array
    {
        if (!array_is_list($payload)) {
            return [];
        }

        $labels = [];
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = $row['label'] ?? null;
            $score = $row['score'] ?? null;
            if (!is_string($label) || trim($label) === '' || !is_numeric($score)) {
                continue;
            }

            $labels[] = [
                'label' => trim($label),
                'score' => (float) $score,
            ];
        }

        if ($labels === []) {
            return [];
        }

        usort($labels, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $labels;
    }

    /**
     * @param array<mixed> $payload
     */
    private function extractErrorMessage(array $payload): string
    {
        // Common direct error keys
        $message = $payload['error'] ?? $payload['message'] ?? null;
        if (is_string($message)) {
            return trim($message);
        }

        // Common wrapped errors
        if (isset($payload[0]) && is_array($payload[0])) {
            $message = $payload[0]['error'] ?? $payload[0]['message'] ?? null;
            if (is_string($message)) {
                return trim($message);
            }
        }

        return '';
    }

    /**
     * @param array<mixed> $payload
     */
    private function truncateJson(array $payload): string
    {
        $json = json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '[unserializable payload]';
        }

        $limit = 500;
        if (mb_strlen($json) <= $limit) {
            return $json;
        }

        return mb_substr($json, 0, $limit) . '...';
    }
}
