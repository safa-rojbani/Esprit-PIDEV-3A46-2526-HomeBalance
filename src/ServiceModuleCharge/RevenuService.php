<?php

namespace App\ServiceModuleCharge;

use App\Entity\Family;
use App\Repository\RevenuRepository;
use App\Repository\HistoriqueAchatRepository;

final class RevenuService
{
    public function __construct(
        private RevenuRepository $revenuRepo,
        private HistoriqueAchatRepository $depenseRepo,
    ) {}

    // ✅ Mode "avec family" (quand auth prête)
    public function totalRevenus(Family $family): string
    {
        return $this->revenuRepo->sumByFamily($family);
    }

    public function totalDepenses(Family $family): string
    {
        return $this->depenseRepo->sumByFamily($family);
    }

    public function solde(Family $family): string
    {
        return $this->sub($this->totalRevenus($family), $this->totalDepenses($family));
    }

    // ✅ Mode "DEV" sans user/family (comme Achat)
    public function totalRevenusAll(): string
    {
        return $this->revenuRepo->sumAll();
    }

    public function totalDepensesAll(): string
    {
        return $this->depenseRepo->sumAll();
    }

    public function soldeAll(): string
    {
        return $this->sub($this->totalRevenusAll(), $this->totalDepensesAll());
    }

    // --- helper ---
    private function sub(string $a, string $b): string
    {
        if (function_exists('bcsub')) {
            return bcsub($a, $b, 2);
        }
        return number_format(((float)$a - (float)$b), 2, '.', '');
    }
}
