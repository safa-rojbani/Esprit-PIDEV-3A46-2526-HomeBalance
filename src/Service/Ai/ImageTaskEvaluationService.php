<?php

namespace App\Service\Ai;

use App\Entity\AiImageEvaluation;
use App\Entity\TaskCompletion;
use App\Enum\AiEvaluationDecision;
use App\Enum\AiEvaluationStatus;
use App\Service\TaskScoringService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ImageTaskEvaluationService
{
    public function __construct(
        private readonly OpenAiVisionProvider $openAiVisionProvider,
        private readonly GeminiVisionProvider $geminiVisionProvider,
        private readonly GoogleVisionProvider $googleVisionProvider,
        private readonly AzureVisionProvider $azureVisionProvider,
        private readonly TaskScoringService $taskScoringService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(string:AI_VISION_PROVIDER)%')]
        private readonly string $configuredProvider,
        #[Autowire('%env(int:AI_PASS_SCORE)%')]
        private readonly int $passScore,
        #[Autowire('%env(float:AI_PASS_CONFIDENCE)%')]
        private readonly float $passConfidence,
        #[Autowire('%env(int:AI_FAIL_SCORE)%')]
        private readonly int $failScore,
        #[Autowire('%env(float:AI_REVIEW_CONFIDENCE)%')]
        private readonly float $reviewConfidence,
        #[Autowire('%env(bool:AI_AUTO_APPROVE_ENABLED)%')]
        private readonly bool $autoApproveEnabled,
        #[Autowire('%env(bool:AI_AUTO_REJECT_ENABLED)%')]
        private readonly bool $autoRejectEnabled,
    ) {
    }

    /**
     * @return array{pointsAwarded: int, familyId: ?int}
     */
    public function process(TaskCompletion $completion): array
    {
        $evaluation = $completion->getAiEvaluation();
        if (!$evaluation instanceof AiImageEvaluation) {
            $evaluation = new AiImageEvaluation();
            $evaluation->setCompletion($completion);
            $evaluation->setCreatedAt(new \DateTimeImmutable());
            $completion->setAiEvaluation($evaluation);
        }

        $provider = $this->resolveProvider();
        $evaluation->setProvider($provider->getProviderName());
        $evaluation->setModel($provider->getModelName());
        $evaluation->setStatus(AiEvaluationStatus::PENDING);
        $evaluation->setDecision(null);
        $evaluation->setErrorMessage(null);
        $evaluation->setProcessedAt(null);

        $absoluteImagePath = sprintf(
            '%s/public/uploads/proofs/%s',
            rtrim($this->projectDir, '\\/'),
            (string) $completion->getProof()
        );

        $pointsAwarded = 0;
        $familyId = $completion->getTask()?->getFamily()?->getId();

        try {
            $analysis = $provider->analyzeRoomImage($absoluteImagePath);
            $evaluation->setStatus(AiEvaluationStatus::SUCCESS);
            $evaluation->setDecision($analysis->decision);
            $evaluation->setTidyScore($analysis->tidyScore);
            $evaluation->setConfidence($analysis->confidence);
            $evaluation->setReasonShort($analysis->reasonShort);
            $evaluation->setRawResponse($analysis->rawResponse);
            $evaluation->setProcessedAt(new \DateTimeImmutable());

            // If a human already handled this completion, keep AI as informational only.
            if ($completion->isValidated() !== null) {
                return ['pointsAwarded' => 0, 'familyId' => $familyId];
            }

            $decision = $analysis->decision;
            if (
                $this->autoApproveEnabled
                && $decision === AiEvaluationDecision::PASS
                && $analysis->tidyScore >= $this->passScore
                && $analysis->confidence >= $this->passConfidence
            ) {
                $completion->setIsValidated(true);
                $completion->setValidatedAt(new \DateTimeImmutable());
                $completion->setValidatedBy(null);
                $completion->setParentComment(sprintf(
                    'Auto validated by AI (%s): %s',
                    strtoupper($provider->getProviderName()),
                    $analysis->reasonShort
                ));

                $pointsAwarded = $this->taskScoringService->awardPointsForValidatedCompletion($completion, true);
            } elseif (
                $this->autoRejectEnabled
                && $decision === AiEvaluationDecision::FAIL
                && $analysis->tidyScore <= $this->failScore
                && $analysis->confidence >= $this->reviewConfidence
            ) {
                $completion->setIsValidated(false);
                $completion->setValidatedAt(new \DateTimeImmutable());
                $completion->setValidatedBy(null);
                $completion->setParentComment(sprintf(
                    'Auto rejected by AI (%s): %s',
                    strtoupper($provider->getProviderName()),
                    $analysis->reasonShort
                ));
            } else {
                $completion->setParentComment(sprintf(
                    'AI suggestion (%s): %s (score %d, confidence %.2f). Waiting parent review.',
                    strtoupper($provider->getProviderName()),
                    $analysis->reasonShort,
                    $analysis->tidyScore,
                    $analysis->confidence
                ));
            }
        } catch (\Throwable $e) {
            $evaluation->setStatus(AiEvaluationStatus::FAILED);
            $evaluation->setDecision(AiEvaluationDecision::REVIEW);
            $evaluation->setReasonShort('AI analysis unavailable. Parent review required.');
            $evaluation->setErrorMessage(mb_substr($e->getMessage(), 0, 1000));
            $evaluation->setProcessedAt(new \DateTimeImmutable());
        }

        return ['pointsAwarded' => $pointsAwarded, 'familyId' => $familyId];
    }

    private function resolveProvider(): VisionProviderInterface
    {
        return match (mb_strtolower(trim($this->configuredProvider))) {
            'gemini', 'google_gemini' => $this->geminiVisionProvider,
            'google', 'google_vision' => $this->googleVisionProvider,
            'azure', 'azure_vision' => $this->azureVisionProvider,
            default => $this->openAiVisionProvider,
        };
    }
}
