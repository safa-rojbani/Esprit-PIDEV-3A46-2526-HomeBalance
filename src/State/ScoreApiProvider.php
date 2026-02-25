<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ScoreApiResource;
use App\Entity\Score;
use App\Entity\User;
use App\Repository\ScoreRepository;
use App\Service\ActiveFamilyResolver;
use Symfony\Bundle\SecurityBundle\Security;

final class ScoreApiProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $familyResolver,
        private readonly ScoreRepository $scoreRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $operation instanceof CollectionOperationInterface ? [] : null;
        }

        if ($operation instanceof CollectionOperationInterface) {
            $scores = $this->resolveCollection($user);
            return $this->toRankedResources($scores);
        }

        $scoreId = (int) ($uriVariables['id'] ?? 0);
        if ($scoreId <= 0) {
            return null;
        }

        $score = $this->scoreRepository->find($scoreId);
        if (!$score instanceof Score || !$this->canAccessScore($user, $score)) {
            return null;
        }

        $ranked = $this->toRankedResources([$score]);
        return $ranked[0] ?? null;
    }

    /**
     * @return array<int, Score>
     */
    private function resolveCollection(User $user): array
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $this->scoreRepository->findAllWithRelations();
        }

        $family = $this->familyResolver->resolveForUser($user);
        if ($family === null) {
            return [];
        }

        return $this->scoreRepository->findAllWithRelationsForFamily($family);
    }

    private function canAccessScore(User $user, Score $score): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $family = $this->familyResolver->resolveForUser($user);
        if ($family === null) {
            return false;
        }

        return $score->getFamily()?->getId() === $family->getId();
    }

    /**
     * @param array<int, Score> $scores
     * @return array<int, ScoreApiResource>
     */
    private function toRankedResources(array $scores): array
    {
        usort($scores, static function (Score $a, Score $b): int {
            return ($b->getTotalPoints() ?? 0) <=> ($a->getTotalPoints() ?? 0);
        });

        $resources = [];
        $position = 0;
        $currentRank = 0;
        $lastPoints = null;

        foreach ($scores as $score) {
            $scoreId = $score->getId();
            $member = $score->getUser();
            $memberId = $member?->getId();
            if ($scoreId === null || !$member instanceof User || $memberId === null) {
                continue;
            }

            ++$position;
            $points = (int) ($score->getTotalPoints() ?? 0);
            if ($lastPoints !== $points) {
                $currentRank = $position;
                $lastPoints = $points;
            }

            $displayName = trim(((string) $member->getFirstName()).' '.((string) $member->getLastName()));
            if ($displayName === '') {
                $displayName = (string) $member->getEmail();
            }

            $resource = new ScoreApiResource();
            $resource->id = $scoreId;
            $resource->userId = $memberId;
            $resource->memberName = $displayName;
            $resource->totalPoints = $points;
            $resource->rank = $currentRank;
            $resource->lastUpdated = $score->getLastUpdated()?->format(\DateTimeInterface::ATOM);

            $resources[] = $resource;
        }

        return $resources;
    }
}

