<?php

namespace App\ServiceModuleCharge;

use App\Entity\Family;
use App\Repository\HistoriqueAchatRepository;
use App\Repository\RevenuRepository;

final class MonthlyReportService
{
    public function __construct(
        private readonly HistoriqueAchatRepository $historiqueAchatRepository,
        private readonly RevenuRepository $revenuRepository,
    ) {
    }

    /**
     * @return array{
     *   monthLabel: string,
     *   previousMonthLabel: string,
     *   expensesCurrent: float,
     *   expensesPrevious: float,
     *   incomesCurrent: float,
     *   incomesPrevious: float,
     *   expenseChangePercent: float,
     *   incomeChangePercent: float,
     *   capacityCurrent: float,
     *   topCategoryName: string,
     *   topCategoryCurrent: float,
     *   topCategoryPrevious: float,
     *   topCategoryChangePercent: float,
     *   categoryTotalsCurrent: array<int, array{category: string, total: float}>
     * }
     */
    public function build(Family $family, \DateTimeImmutable $monthStart): array
    {
        $currentStart = $monthStart->setTime(0, 0, 0);
        $currentEnd = $currentStart->modify('first day of next month');

        $previousStart = $currentStart->modify('first day of previous month');
        $previousEnd = $currentStart;

        $expensesCurrent = (float) $this->historiqueAchatRepository->sumByFamilyAndPeriod($family, $currentStart, $currentEnd);
        $expensesPrevious = (float) $this->historiqueAchatRepository->sumByFamilyAndPeriod($family, $previousStart, $previousEnd);

        $incomesCurrent = (float) $this->revenuRepository->sumByFamilyAndPeriod($family, $currentStart, $currentEnd);
        $incomesPrevious = (float) $this->revenuRepository->sumByFamilyAndPeriod($family, $previousStart, $previousEnd);

        $currentCategoryRows = $this->historiqueAchatRepository->categoryTotalsByFamilyAndPeriod($family, $currentStart, $currentEnd);
        $previousCategoryRows = $this->historiqueAchatRepository->categoryTotalsByFamilyAndPeriod($family, $previousStart, $previousEnd);

        $currentCategories = [];
        foreach ($currentCategoryRows as $row) {
            $currentCategories[] = [
                'category' => $row['category'],
                'total' => (float) $row['total'],
            ];
        }

        $previousByCategory = [];
        foreach ($previousCategoryRows as $row) {
            $previousByCategory[(string) $row['category']] = (float) $row['total'];
        }

        $topCategoryName = 'Aucune';
        $topCategoryCurrent = 0.0;
        if ($currentCategories !== []) {
            $topCategoryName = (string) $currentCategories[0]['category'];
            $topCategoryCurrent = (float) $currentCategories[0]['total'];
        }
        $topCategoryPrevious = (float) ($previousByCategory[$topCategoryName] ?? 0.0);

        return [
            'monthLabel' => $currentStart->format('F Y'),
            'previousMonthLabel' => $previousStart->format('F Y'),
            'expensesCurrent' => round($expensesCurrent, 2),
            'expensesPrevious' => round($expensesPrevious, 2),
            'incomesCurrent' => round($incomesCurrent, 2),
            'incomesPrevious' => round($incomesPrevious, 2),
            'expenseChangePercent' => $this->percentChange($expensesCurrent, $expensesPrevious),
            'incomeChangePercent' => $this->percentChange($incomesCurrent, $incomesPrevious),
            'capacityCurrent' => round($incomesCurrent - $expensesCurrent, 2),
            'topCategoryName' => $topCategoryName,
            'topCategoryCurrent' => round($topCategoryCurrent, 2),
            'topCategoryPrevious' => round($topCategoryPrevious, 2),
            'topCategoryChangePercent' => $this->percentChange($topCategoryCurrent, $topCategoryPrevious),
            'categoryTotalsCurrent' => $currentCategories,
        ];
    }

    private function percentChange(float $current, float $previous): float
    {
        if ($previous <= 0.0) {
            if ($current <= 0.0) {
                return 0.0;
            }

            return 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
