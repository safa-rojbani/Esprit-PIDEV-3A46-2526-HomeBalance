<?php

namespace App\DTO;

/**
 * @phpstan-type PreferenceData array{
 *     topics: list<string>,
 *     channels: list<string>,
 *     quietStart: ?string,
 *     quietEnd: ?string,
 *     weeklySummary: bool,
 *     updatedAt: ?string
 * }
 */
final class PreferenceUpdateResult
{
    /**
     * @param PreferenceData $data
     * @param list<string> $errors
     */
    public function __construct(
        public bool $success,
        public array $data,
        public array $errors = [],
    ) {
    }
}
