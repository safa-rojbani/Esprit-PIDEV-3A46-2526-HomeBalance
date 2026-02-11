<?php

namespace App\Service;

use App\DTO\FamilyOnboardingResult;
use App\Entity\User;
use LogicException;

final class FamilyOnboardingService
{
    public function __construct(
        private readonly ActiveFamilyResolver $familyResolver,
        private readonly FamilyManager $familyManager,
        private readonly AuditTrailService $auditTrailService,
        private readonly NotificationService $notificationService,
        private readonly BadgeAwardingService $badgeAwardingService,
    ) {
    }

    public function createFamily(User $user, string $name): FamilyOnboardingResult
    {
        if ($this->familyResolver->hasActiveFamily($user)) {
            return new FamilyOnboardingResult(false, null, ['You already belong to a family.']);
        }

        $name = trim($name);
        if ($name === '') {
            return new FamilyOnboardingResult(false, null, ['Please provide a family name.']);
        }

        try {
            $family = $this->familyManager->createFamily($user, $name);
        } catch (LogicException $exception) {
            return new FamilyOnboardingResult(false, null, [$exception->getMessage()]);
        }

        $payload = [
            'familyId' => $family->getId(),
            'familyName' => $family->getName(),
        ];

        $this->auditTrailService->record($user, 'family.created', $payload, $family);
        $this->notificationService->sendAccountNotification($user, 'family_created', $payload);
        $this->badgeAwardingService->awardAllIfReady();

        return new FamilyOnboardingResult(true, $family, [], 'Family created. Share your code to invite others.');
    }

    public function joinFamily(User $user, string $code): FamilyOnboardingResult
    {
        if ($this->familyResolver->hasActiveFamily($user)) {
            return new FamilyOnboardingResult(false, null, ['You already belong to a family.']);
        }

        $code = strtoupper(trim($code));
        if ($code === '') {
            return new FamilyOnboardingResult(false, null, ['A join code is required.']);
        }

        try {
            $family = $this->familyManager->joinFamilyByCode($user, $code);
        } catch (LogicException $exception) {
            return new FamilyOnboardingResult(false, null, [$exception->getMessage()]);
        }

        $payload = [
            'familyId' => $family->getId(),
            'familyName' => $family->getName(),
            'joinCode' => $code,
        ];

        $this->auditTrailService->record($user, 'family.joined', $payload, $family);
        $this->notificationService->sendAccountNotification($user, 'family_joined', $payload);
        $this->badgeAwardingService->awardAllIfReady();

        return new FamilyOnboardingResult(true, $family, [], 'Welcome aboard! Your household was updated.');
    }
}
