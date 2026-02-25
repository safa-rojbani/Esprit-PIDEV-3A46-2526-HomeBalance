<?php

namespace App\Service\Ai;

interface VisionProviderInterface
{
    public function getProviderName(): string;

    public function getModelName(): ?string;

    public function analyzeRoomImage(string $absoluteImagePath): VisionAnalysisResult;
}

