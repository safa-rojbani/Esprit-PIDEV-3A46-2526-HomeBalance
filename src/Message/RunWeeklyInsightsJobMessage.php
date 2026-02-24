<?php

namespace App\Message;

final class RunWeeklyInsightsJobMessage
{
    public function __construct(
        public readonly bool $force = false,
    ) {
    }
}
