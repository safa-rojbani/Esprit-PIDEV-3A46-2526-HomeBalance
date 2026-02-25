<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ScoreHistoryApiResource;
use App\Entity\ScoreHistory;
use App\Entity\User;
use App\Repository\ScoreHistoryRepository;
use App\Service\ActiveFamilyResolver;
use Symfony\Bundle\SecurityBundle\Security;

final class ScoreHistoryApiProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $familyResolver,
        private readonly ScoreHistoryRepository $scoreHistoryRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $operation instanceof CollectionOperationInterface ? [] : null;
        }

        if ($operation instanceof CollectionOperationInterface) {
            $entries = $this->resolveCollection($user);

            return array_map(
                fn (ScoreHistory $entry): ScoreHistoryApiResource => $this->toResource($entry),
                $entries
            );
        }

        $entryId = (int) ($uriVariables['id'] ?? 0);
        if ($entryId <= 0) {
            return null;
        }

        $entry = $this->scoreHistoryRepository->find($entryId);
        if (!$entry instanceof ScoreHistory || !$this->canAccessEntry($user, $entry)) {
            return null;
        }

        return $this->toResource($entry);
    }

    /**
     * @return array<int, ScoreHistory>
     */
    private function resolveCollection(User $user): array
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $this->scoreHistoryRepository->createQueryBuilder('h')
                ->addSelect('s', 'u', 't')
                ->join('h.score', 's')
                ->join('s.user', 'u')
                ->join('h.task', 't')
                ->orderBy('h.createdAt', 'DESC')
                ->setMaxResults(200)
                ->getQuery()
                ->getResult();
        }

        $family = $this->familyResolver->resolveForUser($user);
        if ($family === null) {
            return [];
        }

        return $this->scoreHistoryRepository->findForFamilyWithFilters($family, '', null, 'newest', 200);
    }

    private function canAccessEntry(User $user, ScoreHistory $entry): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $family = $this->familyResolver->resolveForUser($user);
        if ($family === null) {
            return false;
        }

        return $entry->getScore()?->getFamily()?->getId() === $family->getId();
    }

    private function toResource(ScoreHistory $entry): ScoreHistoryApiResource
    {
        $resource = new ScoreHistoryApiResource();
        $resource->id = (int) $entry->getId();
        $resource->points = (int) ($entry->getPoints() ?? 0);
        $resource->taskTitle = (string) ($entry->getTask()?->getTitle() ?? '');

        $member = $entry->getScore()?->getUser();
        $displayName = trim(((string) $member?->getFirstName()).' '.((string) $member?->getLastName()));
        if ($displayName === '') {
            $displayName = (string) ($member?->getEmail() ?? '');
        }
        $resource->memberName = $displayName;
        $resource->awardedByAi = $entry->isAwardedByAi();
        $resource->source = $entry->isAwardedByAi() ? 'ai' : 'manual';
        $resource->createdAt = $entry->getCreatedAt()?->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
