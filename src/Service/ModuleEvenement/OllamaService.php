<?php

namespace App\Service\ModuleEvenement;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaService
{
    private const BASE_URL = 'http://localhost:11434';

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function generateDescription(string $titre, string $lieu, string $typeNom): string
    {
        $prompt = sprintf(
            "Tu es un assistant qui redige des descriptions d'evenements familiaux en francais. Genere une description courte, chaleureuse et professionnelle (3 a 5 phrases maximum) pour cet evenement :\nTitre : %s\nType : %s\nLieu : %s\nReponds uniquement avec la description, sans introduction.",
            $titre,
            $typeNom,
            $lieu
        );

        try {
            $response = $this->httpClient->request('POST', self::BASE_URL . '/api/generate', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'mistral',
                    'prompt' => $prompt,
                    'stream' => false,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray(false);
            if (!isset($data['response']) || !is_string($data['response'])) {
                throw new \RuntimeException('Invalid response');
            }

            return trim($data['response']);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Le service IA est indisponible. Verifiez qu'Ollama tourne sur le port 11434."
            );
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/api/tags', [
                'timeout' => 10,
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
