<?php

namespace App\Service\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AzureVisionProvider implements VisionProviderInterface
{
    private const POSITIVE_KEYWORDS = ['clean', 'tidy', 'organized', 'neat', 'orderly', 'rang', 'propre'];
    private const NEGATIVE_KEYWORDS = ['messy', 'clutter', 'dirty', 'trash', 'disorder', 'desordre', 'encombre'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:AZURE_VISION_ENDPOINT)%')]
        private readonly string $endpoint,
        #[Autowire('%env(string:AZURE_VISION_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function getProviderName(): string
    {
        return 'azure';
    }

    public function getModelName(): string
    {
        return 'computer-vision-v3.2';
    }

    public function analyzeRoomImage(string $absoluteImagePath): VisionAnalysisResult
    {
        if (trim($this->endpoint) === '' || trim($this->apiKey) === '') {
            throw new \RuntimeException('AZURE_VISION_ENDPOINT or AZURE_VISION_KEY is missing.');
        }

        if (!is_file($absoluteImagePath)) {
            throw new \RuntimeException('Image file not found for Azure Vision analysis.');
        }

        $rawImage = file_get_contents($absoluteImagePath);
        if ($rawImage === false) {
            throw new \RuntimeException('Unable to read image content for Azure Vision analysis.');
        }

        $endpoint = rtrim($this->endpoint, '/');
        $url = $endpoint.'/vision/v3.2/analyze?visualFeatures=Description,Tags,Objects';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->apiKey,
                    'Content-Type' => 'application/octet-stream',
                ],
                'body' => $rawImage,
                'timeout' => 35,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Azure Vision request failed: '.$e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf('Azure Vision API error (HTTP %d).', $statusCode));
        }

        $tokens = [];
        $confidences = [];

        foreach (($payload['tags'] ?? []) as $tag) {
            $name = mb_strtolower((string) ($tag['name'] ?? ''));
            if ($name !== '') {
                $tokens[] = $name;
                $confidences[] = (float) ($tag['confidence'] ?? 0.0);
            }
        }

        foreach (($payload['objects'] ?? []) as $object) {
            $name = mb_strtolower((string) ($object['object'] ?? ''));
            if ($name !== '') {
                $tokens[] = $name;
                $confidences[] = (float) ($object['confidence'] ?? 0.0);
            }
        }

        foreach (($payload['description']['captions'] ?? []) as $caption) {
            $text = mb_strtolower((string) ($caption['text'] ?? ''));
            if ($text !== '') {
                $tokens[] = $text;
                $confidences[] = (float) ($caption['confidence'] ?? 0.0);
            }
        }

        $positiveHits = $this->countKeywordHits($tokens, self::POSITIVE_KEYWORDS);
        $negativeHits = $this->countKeywordHits($tokens, self::NEGATIVE_KEYWORDS);

        $score = 55 + ($positiveHits * 12) - ($negativeHits * 15);
        $score = VisionAnalysisResult::clampScore($score);

        $baseConfidence = $confidences === [] ? 0.55 : array_sum($confidences) / count($confidences);
        $confidence = VisionAnalysisResult::clampConfidence((float) $baseConfidence);

        $decision = VisionAnalysisResult::decisionFromScore($score, $confidence);
        $reason = sprintf(
            'Azure Vision signals: +%d clean tags, -%d messy tags.',
            $positiveHits,
            $negativeHits
        );

        return VisionAnalysisResult::fromValues(
            $score,
            $confidence,
            $decision,
            $reason,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @param array<int, string> $tokens
     * @param array<int, string> $keywords
     */
    private function countKeywordHits(array $tokens, array $keywords): int
    {
        $hits = 0;
        foreach ($tokens as $token) {
            foreach ($keywords as $keyword) {
                if (str_contains($token, $keyword)) {
                    ++$hits;
                    break;
                }
            }
        }

        return $hits;
    }
}

