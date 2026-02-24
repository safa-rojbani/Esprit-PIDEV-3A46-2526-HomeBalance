<?php

namespace App\ServiceModuleCharge;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MonthlyReportAiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $geminiApiKey = '',
    ) {
    }

    /**
     * @param array<string, mixed> $report
     * @return array{text: string, provider: string, fallback: bool}
     */
    public function generate(array $report): array
    {
        $apiKey = $this->geminiApiKey !== ''
            ? $this->geminiApiKey
            : (string) (getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ''));

        if ($apiKey === '') {
            return [
                'text' => $this->buildFallback($report),
                'provider' => 'fallback_rule_based',
                'fallback' => true,
            ];
        }

        $prompt = $this->buildPrompt($report);
        $models = ['gemini-2.5-flash', 'gemini-flash-latest', 'gemini-2.0-flash-lite', 'gemini-2.0-flash', 'gemini-1.5-flash'];

        foreach ($models as $model) {
            $url = sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                $model,
                $apiKey
            );

            try {
                $response = $this->httpClient->request('POST', $url, [
                    'timeout' => 15,
                    'json' => [
                        'contents' => [[
                            'parts' => [[
                                'text' => $prompt,
                            ]],
                        ]],
                    ],
                ]);
                $payload = $response->toArray(false);
                $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (\is_string($text) && trim($text) !== '') {
                    return [
                        'text' => trim($text),
                        'provider' => 'gemini:'.$model,
                        'fallback' => false,
                    ];
                }
            } catch (TransportExceptionInterface) {
                // try next model
            } catch (\Throwable) {
                // try next model
            }
        }

        return [
            'text' => $this->buildFallback($report),
            'provider' => 'fallback_rule_based',
            'fallback' => true,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    private function buildPrompt(array $report): string
    {
        return sprintf(
            "Tu es un conseiller budget familial. Rédige un résumé en français (80-120 mots) et 3 suggestions concrètes.\n".
            "Données:\n".
            "- Dépenses mois courant: %.2f TND\n".
            "- Dépenses mois précédent: %.2f TND\n".
            "- Variation dépenses: %.2f%%\n".
            "- Revenus mois courant: %.2f TND\n".
            "- Revenus mois précédent: %.2f TND\n".
            "- Variation revenus: %.2f%%\n".
            "- Capacité actuelle (revenus-dépenses): %.2f TND\n".
            "- Catégorie principale: %s\n".
            "- Montant catégorie principale courant: %.2f TND\n".
            "- Variation catégorie principale: %.2f%%\n".
            "Contraintes: style clair, pas de markdown, pas de JSON.",
            (float) ($report['expensesCurrent'] ?? 0),
            (float) ($report['expensesPrevious'] ?? 0),
            (float) ($report['expenseChangePercent'] ?? 0),
            (float) ($report['incomesCurrent'] ?? 0),
            (float) ($report['incomesPrevious'] ?? 0),
            (float) ($report['incomeChangePercent'] ?? 0),
            (float) ($report['capacityCurrent'] ?? 0),
            (string) ($report['topCategoryName'] ?? 'Aucune'),
            (float) ($report['topCategoryCurrent'] ?? 0),
            (float) ($report['topCategoryChangePercent'] ?? 0),
        );
    }

    /**
     * @param array<string, mixed> $report
     */
    private function buildFallback(array $report): string
    {
        $expenseChange = (float) ($report['expenseChangePercent'] ?? 0);
        $incomeChange = (float) ($report['incomeChangePercent'] ?? 0);
        $capacity = (float) ($report['capacityCurrent'] ?? 0);
        $topCategory = (string) ($report['topCategoryName'] ?? 'Aucune');
        $topCategoryChange = (float) ($report['topCategoryChangePercent'] ?? 0);

        $trend = $expenseChange > 0
            ? sprintf('Les dépenses ont augmenté de %.2f%%.', $expenseChange)
            : sprintf('Les dépenses ont baissé de %.2f%%.', abs($expenseChange));

        $incomeTrend = $incomeChange > 0
            ? sprintf('Les revenus progressent de %.2f%%.', $incomeChange)
            : sprintf('Les revenus reculent de %.2f%%.', abs($incomeChange));

        $capacityText = $capacity >= 0
            ? sprintf('Votre capacité mensuelle estimée est de %.2f TND.', $capacity)
            : sprintf('Votre budget est en déficit de %.2f TND.', abs($capacity));

        return trim(sprintf(
            "%s %s Catégorie principale: %s (variation %.2f%%). %s Suggestions: fixer un plafond pour %s, suivre les achats hebdomadaires, et automatiser une alerte quand une catégorie dépasse 20%% du budget.",
            $trend,
            $incomeTrend,
            $topCategory,
            $topCategoryChange,
            $capacityText,
            $topCategory
        ));
    }
}
