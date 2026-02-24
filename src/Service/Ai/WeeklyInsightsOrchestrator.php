<?php

namespace App\Service\Ai;

use App\Entity\Family;
use App\Entity\WeeklyAiInsight;
use App\Repository\FamilyRepository;
use App\Repository\WeeklyAiInsightRepository;
use Doctrine\ORM\EntityManagerInterface;

final class WeeklyInsightsOrchestrator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WeeklyAiInsightRepository $weeklyAiInsightRepository,
        private readonly FamilyRepository $familyRepository,
        private readonly WeeklyInsightsDataBuilder $weeklyInsightsDataBuilder,
        private readonly WeeklyInsightsAiService $weeklyInsightsAiService,
    ) {
    }

    public function generateForFamily(
        Family $family,
        ?\DateTimeImmutable $weekStart = null,
        bool $force = false
    ): WeeklyAiInsight {
        $weekStart = $this->resolveWeekStart($weekStart);
        $weekEnd = $weekStart->modify('+7 days');

        $insight = $this->weeklyAiInsightRepository->findOneForFamilyAndWeek($family, $weekStart);
        if ($insight instanceof WeeklyAiInsight && !$force) {
            return $insight;
        }

        $dataset = $this->weeklyInsightsDataBuilder->build($family, $weekStart, $weekEnd);
        $ai = $this->weeklyInsightsAiService->generate($dataset);

        if (!$insight instanceof WeeklyAiInsight) {
            $insight = new WeeklyAiInsight();
            $insight->setFamily($family);
            $insight->setWeekStart($weekStart);
            $insight->setWeekEnd($weekEnd);
            $this->entityManager->persist($insight);
        }

        $summary = $ai['summary'];
        if (!is_array($summary)) {
            $summary = [];
        }

        $insight->setStatus((string) ($ai['status'] ?? 'FALLBACK'));
        $insight->setProvider((string) ($ai['provider'] ?? 'local'));
        $insight->setModel(is_string($ai['model'] ?? null) ? $ai['model'] : null);
        $insight->setRawResponse(is_string($ai['rawResponse'] ?? null) ? $ai['rawResponse'] : null);
        $insight->setErrorMessage(is_string($ai['error'] ?? null) ? $ai['error'] : null);
        $insight->setGeneratedAt(new \DateTimeImmutable());
        $insight->setPayload([
            'period' => $dataset['period'] ?? [],
            'familyTotals' => $dataset['familyTotals'] ?? [],
            'mostImproved' => $summary['mostImproved'] ?? [],
            'familyMomentum' => $summary['familyMomentum'] ?? '',
            'blockingTasks' => $summary['blockingTasks'] ?? [],
            'recommendations' => $summary['recommendations'] ?? [],
            'engagement' => $summary['engagement'] ?? [],
            'sourceStatus' => $ai['status'] ?? 'FALLBACK',
        ]);

        $this->entityManager->flush();

        return $insight;
    }

    public function generateForAllFamilies(?\DateTimeImmutable $weekStart = null, bool $force = false): int
    {
        $count = 0;
        foreach ($this->familyRepository->findAll() as $family) {
            if (!$family instanceof Family) {
                continue;
            }

            $this->generateForFamily($family, $weekStart, $force);
            ++$count;
        }

        return $count;
    }

    private function resolveWeekStart(?\DateTimeImmutable $weekStart = null): \DateTimeImmutable
    {
        $base = $weekStart ?? new \DateTimeImmutable();

        return $base->modify('monday this week')->setTime(0, 0, 0);
    }
}
