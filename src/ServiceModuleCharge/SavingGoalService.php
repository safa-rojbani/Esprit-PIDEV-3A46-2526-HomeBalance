<?php

namespace App\ServiceModuleCharge;

use App\Entity\Family;
use App\Entity\SavingGoal;
use App\Repository\HistoriqueAchatRepository;
use App\Repository\RevenuRepository;

final class SavingGoalService
{
    public function __construct(
        private RevenuRepository $revenuRepository,
        private HistoriqueAchatRepository $historiqueAchatRepository,
    ) {
    }

    public function monthlyCapacity(Family $family, int $months = 3): string
    {
        $avgIncome = (float) $this->revenuRepository->averageMonthlyByFamily($family, $months);
        $avgExpense = (float) $this->historiqueAchatRepository->averageMonthlyByFamily($family, $months);
        $capacity = max(0, $avgIncome - $avgExpense);

        return number_format($capacity, 2, '.', '');
    }

    /**
     * @return array{
     *   remaining: string,
     *   progress: float,
     *   monthlyRequired: ?string,
     *   estimatedDate: ?\DateTimeImmutable,
     *   status: string
     * }
     */
    public function buildPlan(SavingGoal $goal, Family $family): array
    {
        $target = (float) ($goal->getTargetAmount() ?? '0');
        $current = (float) $goal->getCurrentAmount();
        $remaining = max(0, $target - $current);
        $progress = $target > 0 ? min(100, ($current / $target) * 100) : 0.0;

        $monthlyCapacity = (float) $this->monthlyCapacity($family);

        $monthlyRequired = null;
        if ($goal->getTargetDate() !== null && $remaining > 0) {
            $monthsRemaining = $this->monthsUntil($goal->getTargetDate());
            $monthlyRequired = number_format($remaining / $monthsRemaining, 2, '.', '');
        }

        $estimatedDate = null;
        if ($remaining <= 0) {
            $estimatedDate = new \DateTimeImmutable('today');
        } elseif ($monthlyCapacity > 0) {
            $monthsNeeded = (int) ceil($remaining / $monthlyCapacity);
            $estimatedDate = (new \DateTimeImmutable('first day of this month'))->modify(sprintf('+%d months', $monthsNeeded));
        }

        $status = 'en_cours';
        if ($remaining <= 0) {
            $status = 'atteint';
        } elseif ($goal->getTargetDate() !== null && $estimatedDate !== null && $estimatedDate > $goal->getTargetDate()) {
            $status = 'en_retard';
        }

        return [
            'remaining' => number_format($remaining, 2, '.', ''),
            'progress' => round($progress, 1),
            'monthlyRequired' => $monthlyRequired,
            'estimatedDate' => $estimatedDate,
            'status' => $status,
        ];
    }

    private function monthsUntil(\DateTimeImmutable $targetDate): int
    {
        $today = new \DateTimeImmutable('today');
        if ($targetDate <= $today) {
            return 1;
        }

        $diff = $today->diff($targetDate);
        $months = ($diff->y * 12) + $diff->m + ($diff->d > 0 ? 1 : 0);

        return max(1, $months);
    }
}
