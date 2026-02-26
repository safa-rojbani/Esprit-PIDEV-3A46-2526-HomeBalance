<?php

declare(strict_types=1);

namespace App\Service\Ai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? '';
        if ($apiKey === '') {
            throw new \InvalidArgumentException('GEMINI_API_KEY environment variable is not set');
        }
    }

    public function generate(string $model, string $systemPrompt, string $userContent): string
    {
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? '';
        $modelName = $model !== '' ? $model : ($_ENV['GEMINI_MODEL'] ?? $_SERVER['GEMINI_MODEL'] ?? 'gemini-1.5-pro');

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s',
            urlencode($modelName),
            urlencode($apiKey),
        );

        try {
            $response = $this->httpClient->request('POST', $url, [
                'timeout' => 5,
                'json' => [
                    'contents' => [[
                        'role'  => 'user',
                        'parts' => [
                            ['text' => $systemPrompt . "\n\n" . $userContent],
                        ],
                    ]],
                ],
            ]);

            $data = $response->toArray(false);

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } catch (\Throwable) {
            // On any error or timeout, degrade gracefully and return empty text
            return '';
        }
    }
}

