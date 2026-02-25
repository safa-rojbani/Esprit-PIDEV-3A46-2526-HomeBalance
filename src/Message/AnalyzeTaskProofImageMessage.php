<?php

namespace App\Message;

final class AnalyzeTaskProofImageMessage
{
    public function __construct(
        public readonly int $completionId,
    ) {
    }
}

