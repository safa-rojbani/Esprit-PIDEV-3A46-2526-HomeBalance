<?php

namespace App\Service\Ai;

use App\Enum\AiEvaluationDecision;

final class VisionAnalysisResult
{
    public function __construct(
        public readonly int $tidyScore,
        public readonly float $confidence,
        public readonly AiEvaluationDecision $decision,
        public readonly string $reasonShort,
        public readonly ?string $rawResponse = null,
    ) {
    }

    public static function fromValues(
        int $tidyScore,
        float $confidence,
        AiEvaluationDecision $decision,
        string $reasonShort,
        ?string $rawResponse = null,
    ): self {
        return new self(
            self::clampScore($tidyScore),
            self::clampConfidence($confidence),
            $decision,
            trim($reasonShort) === '' ? 'No reason provided.' : trim($reasonShort),
            $rawResponse
        );
    }

    public static function decisionFromScore(int $score, float $confidence): AiEvaluationDecision
    {
        if ($score >= 70 && $confidence >= 0.8) {
            return AiEvaluationDecision::PASS;
        }

        if ($score <= 35 || $confidence < 0.5) {
            return AiEvaluationDecision::FAIL;
        }

        return AiEvaluationDecision::REVIEW;
    }

    public static function clampScore(int $score): int
    {
        return max(0, min(100, $score));
    }

    public static function clampConfidence(float $confidence): float
    {
        return max(0.0, min(1.0, $confidence));
    }
}

