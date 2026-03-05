<?php

namespace App\Service\Security;

use App\Entity\BiometricVerificationAttempt;
use App\Entity\User;
use App\Repository\AdminBiometricProfileRepository;
use App\Service\External\FacePlusPlusClient;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class BiometricVerificationService
{
    public const SESSION_KEY = 'biometric_stepup';

    public function __construct(
        private readonly AdminBiometricProfileRepository $profileRepository,
        private readonly FacePlusPlusClient $facePlusPlusClient,
        private readonly TokenCipherService $tokenCipherService,
        private readonly StepUpPolicyService $stepUpPolicyService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RateLimiterFactory $biometricVerifyLimiter,
    ) {
    }

    /**
     * @return array{decision: string, score: ?float, threshold: float}
     */
    public function verify(
        Request $request,
        User $actor,
        string $actionKey,
        UploadedFile $selfie,
        ?User $targetUser = null,
    ): array {
        $limiter = $this->biometricVerifyLimiter->create('bio_' . $actor->getId());
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            return $this->recordAndReturn(
                $actor,
                $actionKey,
                $targetUser,
                null,
                StepUpPolicyService::PASS_THRESHOLD,
                BiometricVerificationAttempt::RESULT_ERROR,
                ['error' => 'rate_limited'],
                $request,
            );
        }

        $profile = $this->profileRepository->findEnabledForUser($actor);
        if ($profile === null) {
            return $this->recordAndReturn(
                $actor,
                $actionKey,
                $targetUser,
                null,
                StepUpPolicyService::PASS_THRESHOLD,
                BiometricVerificationAttempt::RESULT_ERROR,
                ['error' => 'profile_missing'],
                $request,
            );
        }

        try {
            $referenceToken = $this->tokenCipherService->decrypt($profile->getReferenceFaceTokenEncrypted());
            $compare = $this->facePlusPlusClient->verifyReference($referenceToken, $selfie);
            $score = (float) ($compare['confidence'] ?? 0.0);
            $match = (bool) ($compare['match'] ?? false);
            $pass = $this->stepUpPolicyService->passThreshold();
            $fallback = $this->stepUpPolicyService->fallbackThreshold();

            if ($match) {
                $score = max($score, $pass);
            }

            if ($score >= $pass) {
                $this->storeVerifiedSession($request, $actionKey, $targetUser);

                return $this->recordAndReturn(
                    $actor,
                    $actionKey,
                    $targetUser,
                    $score,
                    $pass,
                    BiometricVerificationAttempt::RESULT_PASSED,
                    [
                        'thresholds' => $compare['thresholds'] ?? [],
                        'providerMatch' => $match,
                    ],
                    $request,
                );
            }

            $result = $score >= $fallback
                ? BiometricVerificationAttempt::RESULT_FALLBACK_REQUIRED
                : BiometricVerificationAttempt::RESULT_FAILED;

            return $this->recordAndReturn(
                $actor,
                $actionKey,
                $targetUser,
                $score,
                $pass,
                $result,
                [
                    'thresholds' => $compare['thresholds'] ?? [],
                    'providerMatch' => $match,
                ],
                $request,
            );
        } catch (\Throwable $exception) {
            return $this->recordAndReturn(
                $actor,
                $actionKey,
                $targetUser,
                null,
                StepUpPolicyService::PASS_THRESHOLD,
                BiometricVerificationAttempt::RESULT_ERROR,
                ['error' => $exception->getMessage()],
                $request,
            );
        }
    }

    public function isVerifiedForAction(Request $request, string $actionKey, ?User $targetUser = null): bool
    {
        if (!$this->stepUpPolicyService->isStepUpRequired($actionKey)) {
            return true;
        }

        $session = $request->getSession();

        /** @var array<string, array{verifiedAt: string, targetUserId: ?string}> $records */
        $records = $session->get(self::SESSION_KEY, []);
        $record = $records[$actionKey] ?? null;
        if (!is_array($record)) {
            return false;
        }

        $verifiedAtRaw = $record['verifiedAt'] ?? null;
        if (!is_string($verifiedAtRaw) || $verifiedAtRaw === '') {
            return false;
        }

        $verifiedAt = new DateTimeImmutable($verifiedAtRaw);
        if ($verifiedAt < (new DateTimeImmutable())->modify('-10 minutes')) {
            return false;
        }

        $recordTarget = $record['targetUserId'] ?? null;
        $expectedTarget = $targetUser?->getId();

        return $recordTarget === $expectedTarget;
    }

    private function storeVerifiedSession(Request $request, string $actionKey, ?User $targetUser): void
    {
        $session = $request->getSession();

        /** @var array<string, array{verifiedAt: string, targetUserId: ?string}> $records */
        $records = $session->get(self::SESSION_KEY, []);
        $records[$actionKey] = [
            'verifiedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'targetUserId' => $targetUser?->getId(),
        ];

        $session->set(self::SESSION_KEY, $records);
    }

    public function markVerifiedForAction(Request $request, string $actionKey, ?User $targetUser = null): void
    {
        $this->storeVerifiedSession($request, $actionKey, $targetUser);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{decision: string, score: ?float, threshold: float}
     */
    private function recordAndReturn(
        User $actor,
        string $actionKey,
        ?User $targetUser,
        ?float $score,
        float $threshold,
        string $result,
        array $meta,
        Request $request,
    ): array {
        $attempt = (new BiometricVerificationAttempt())
            ->setActorUser($actor)
            ->setActionKey($actionKey)
            ->setTargetUser($targetUser)
            ->setSimilarityScore($score)
            ->setThresholdUsed($threshold)
            ->setResult($result)
            ->setIpAddress((string) $request->getClientIp() ?: null)
            ->setUserAgent($request->headers->get('User-Agent'))
            ->setProviderResponseMeta($meta);

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        return [
            'decision' => $result,
            'score' => $score,
            'threshold' => $threshold,
        ];
    }
}
