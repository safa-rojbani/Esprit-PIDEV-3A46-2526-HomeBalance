<?php

namespace App\Service\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleVisionProvider implements VisionProviderInterface
{
    private const POSITIVE_KEYWORDS = ['clean', 'tidy', 'organized', 'neat', 'orderly', 'rang', 'propre'];
    private const NEGATIVE_KEYWORDS = ['messy', 'clutter', 'dirty', 'trash', 'disorder', 'desordre', 'encombre'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:GOOGLE_VISION_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function getProviderName(): string
    {
        return 'google';
    }

    public function getModelName(): ?string
    {
        return 'cloud-vision-v1';
    }

    public function analyzeRoomImage(string $absoluteImagePath): VisionAnalysisResult
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('GOOGLE_VISION_API_KEY is missing.');
        }

        if (!is_file($absoluteImagePath)) {
            throw new \RuntimeException('Image file not found for Google Vision analysis.');
        }

        $rawImage = file_get_contents($absoluteImagePath);
        if ($rawImage === false) {
            throw new \RuntimeException('Unable to read image content for Google Vision analysis.');
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                sprintf('https://vision.googleapis.com/v1/images:annotate?key=%s', urlencode($this->apiKey)),
                [
                    'json' => [
                        'requests' => [[
                            'image' => ['content' => base64_encode($rawImage)],
                            'features' => [
                                ['type' => 'LABEL_DETECTION', 'maxResults' => 20],
                                ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 20],
                            ],
                        ]],
                    ],
                    'timeout' => 35,
                ]
            );
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Google Vision request failed: '.$e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf('Google Vision API error (HTTP %d).', $statusCode));
        }

        $answer = $payload['responses'][0] ?? [];
        $labels = $answer['labelAnnotations'] ?? [];
        $objects = $answer['localizedObjectAnnotations'] ?? [];

        $tokens = [];
        $confidences = [];

        foreach ($labels as $label) {
            $desc = mb_strtolower((string) ($label['description'] ?? ''));
            if ($desc !== '') {
                $tokens[] = $desc;
                $confidences[] = (float) ($label['score'] ?? 0.0);
            }
        }
        foreach ($objects as $obj) {
            $name = mb_strtolower((string) ($obj['name'] ?? ''));
            if ($name !== '') {
                $tokens[] = $name;
                $confidences[] = (float) ($obj['score'] ?? 0.0);
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
            'Google Vision signals: +%d clean tags, -%d messy tags.',
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

