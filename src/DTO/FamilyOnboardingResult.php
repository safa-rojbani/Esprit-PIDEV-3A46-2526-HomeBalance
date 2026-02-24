<?php

namespace App\DTO;

use App\Entity\Family;

final class FamilyOnboardingResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public bool $success,
        public ?Family $family = null,
        public array $errors = [],
        public ?string $message = null,
    ) {
    }
}
