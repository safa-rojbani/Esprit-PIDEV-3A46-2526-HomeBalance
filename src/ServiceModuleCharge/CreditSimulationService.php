<?php

namespace App\ServiceModuleCharge;

use App\Entity\Credit;

final class CreditSimulationService
{
    /**
     * @return array{
     *   monthlyPayment: string,
     *   totalRepayment: string,
     *   totalInterest: string
     * }
     */
    public function simulate(Credit $credit): array
    {
        $principal = (float) ($credit->getPrincipal() ?? '0');
        $annualRate = (float) ($credit->getAnnualRate() ?? '0');
        $months = max(1, (int) ($credit->getTermMonths() ?? 1));

        $monthlyRate = $annualRate / 100 / 12;

        if ($monthlyRate <= 0) {
            $monthlyPayment = $principal / $months;
        } else {
            $factor = pow(1 + $monthlyRate, $months);
            $monthlyPayment = $principal * (($monthlyRate * $factor) / ($factor - 1));
        }

        $totalRepayment = $monthlyPayment * $months;
        $totalInterest = max(0, $totalRepayment - $principal);

        return [
            'monthlyPayment' => number_format($monthlyPayment, 2, '.', ''),
            'totalRepayment' => number_format($totalRepayment, 2, '.', ''),
            'totalInterest' => number_format($totalInterest, 2, '.', ''),
        ];
    }
}
