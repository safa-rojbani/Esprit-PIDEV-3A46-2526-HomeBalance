<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Family;
use App\Entity\FamilyBadge;
use App\Entity\User;
use App\Enum\BadgeCode;
use App\Repository\BadgeRepository;
use App\Repository\FamilyBadgeRepository;
use App\Repository\ScoreRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

final class BadgeAwardingService
{
    private const BALANCED_THRESHOLD = 15.0;

    public function __construct(
        private readonly BadgeRepository $badgeRepository,
        private readonly ScoreRepository $scoreRepository,
        private readonly FamilyBadgeRepository $familyBadgeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{userBadgesChanged: int, familyBadgesChanged: int}
     */
    public function awardAll(): array
    {
        $badges = $this->loadBadges();
        $scores = $this->scoreRepository->findAllWithRelations();
        $families = $this->groupScoresByFamily($scores);

        $userChanges = $this->assignHardworkingMembers($families, $badges[BadgeCode::HARDWORKING_MEMBER->value]);
        $familyChanges = 0;
        $familyChanges += $this->assignBalancedFamilies($families, $badges[BadgeCode::BALANCED_FAMILY->value]);
        $familyChanges += $this->assignHardworkingFamilies($families, $badges[BadgeCode::HARDWORKING_FAMILY->value]);

        $this->entityManager->flush();

        return [
            'userBadgesChanged' => $userChanges,
            'familyBadgesChanged' => $familyChanges,
        ];
    }

    /**
     * @return array{userBadgesChanged: int, familyBadgesChanged: int}|null
     */
    public function awardAllIfReady(): ?array
    {
        if (!$this->hasRequiredBadges()) {
            return null;
        }

        return $this->awardAll();
    }

    /**
     * @return array{userBadgesChanged: int, familyBadgesChanged: int}|null
     */
    public function awardForFamilyIfReady(Family $family): ?array
    {
        if (!$this->hasRequiredBadges()) {
            return null;
        }

        $badges = $this->loadBadges();
        $scores = $this->scoreRepository->findAllWithRelationsForFamily($family);
        $families = $this->groupScoresByFamily($scores);

        $userChanges = $this->assignHardworkingMembers($families, $badges[BadgeCode::HARDWORKING_MEMBER->value]);
        $familyChanges = 0;
        $familyChanges += $this->assignBalancedFamilies($families, $badges[BadgeCode::BALANCED_FAMILY->value]);
        $familyChanges += $this->assignHardworkingFamilies($families, $badges[BadgeCode::HARDWORKING_FAMILY->value]);

        $this->entityManager->flush();

        return [
            'userBadgesChanged' => $userChanges,
            'familyBadgesChanged' => $familyChanges,
        ];
    }

    /**
     * @param array<int, array{family: Family, scores: array<int, array{user: User, points: int}>}> $families
     */
    private function assignHardworkingMembers(array $families, Badge $badge): int
    {
        $changes = 0;
        foreach ($families as $bucket) {
            if ($bucket['scores'] === []) {
                continue;
            }

            usort($bucket['scores'], static fn ($a, $b) => $b['points'] <=> $a['points']);
            $topUser = $bucket['scores'][0]['user'];

            foreach ($bucket['scores'] as $entry) {
                $user = $entry['user'];
                $hasBadge = $user->getBadges()->contains($badge);

                if ($user === $topUser) {
                    if (!$hasBadge) {
                        $user->addBadge($badge);
                        $changes++;
                    }
                } elseif ($hasBadge) {
                    $user->removeBadge($badge);
                    $changes++;
                }
            }
        }

        return $changes;
    }

    /**
     * @param array<int, array{family: Family, scores: array<int, array{user: User, points: int}>}> $families
     */
    private function assignBalancedFamilies(array $families, Badge $badge): int
    {
        $qualified = [];
        foreach ($families as $familyId => $bucket) {
            if (count($bucket['scores']) < 2) {
                continue;
            }

            $points = array_map(static fn ($entry) => $entry['points'], $bucket['scores']);
            if ($this->standardDeviation($points) <= self::BALANCED_THRESHOLD) {
                $qualified[$familyId] = $bucket['family'];
            }
        }

        return $this->syncFamilyBadges($qualified, $badge);
    }

    /**
     * @param array<int, array{family: Family, scores: array<int, array{user: User, points: int}>}> $families
     */
    private function assignHardworkingFamilies(array $families, Badge $badge): int
    {
        if ($families === []) {
            return $this->syncFamilyBadges([], $badge);
        }

        $totals = [];
        foreach ($families as $familyId => $bucket) {
            $totals[$familyId] = array_sum(array_map(static fn ($entry) => $entry['points'], $bucket['scores']));
        }

        $max = max($totals);
        if ($max <= 0) {
            return $this->syncFamilyBadges([], $badge);
        }

        $qualified = [];
        foreach ($totals as $familyId => $total) {
            if ($total === $max) {
                $qualified[$familyId] = $families[$familyId]['family'];
            }
        }

        return $this->syncFamilyBadges($qualified, $badge);
    }

    /**
     * @param array<int, Family> $families
     */
    private function syncFamilyBadges(array $families, Badge $badge): int
    {
        $changes = 0;
        $existing = $this->familyBadgeRepository->findByBadge($badge);
        $existingMap = [];

        foreach ($existing as $record) {
            $familyId = $record->getFamily()->getId();
            $existingMap[$familyId] = $record;

            if (!isset($families[$familyId])) {
                $this->entityManager->remove($record);
                $changes++;
            }
        }

        foreach ($families as $familyId => $family) {
            if (!isset($existingMap[$familyId])) {
                $familyBadge = (new FamilyBadge())
                    ->setFamily($family)
                    ->setBadge($badge)
                    ->setAwardedAt(new DateTimeImmutable());
                $this->entityManager->persist($familyBadge);
                $changes++;
            }
        }

        return $changes;
    }

    /**
     * @param array<int, \App\Entity\Score> $scores
     * @return array<int, array{family: Family, scores: array<int, array{user: User, points: int}>}>
     */
    private function groupScoresByFamily(array $scores): array
    {
        $families = [];
        foreach ($scores as $score) {
            $family = $score->getFamily();
            $user = $score->getUser();
            if (!$family || !$user) {
                continue;
            }

            $familyId = $family->getId();
            if ($familyId === null) {
                continue;
            }

            $families[$familyId] ??= [
                'family' => $family,
                'scores' => [],
            ];
            $families[$familyId]['scores'][] = [
                'user' => $user,
                'points' => $score->getTotalPoints() ?? 0,
            ];
        }

        return $families;
    }

    /**
     * @param array<int, int> $values
     */
    private function standardDeviation(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        return sqrt($variance / $count);
    }

    /**
     * @return array<string, Badge>
     */
    private function loadBadges(): array
    {
        $codes = [
            BadgeCode::HARDWORKING_MEMBER,
            BadgeCode::BALANCED_FAMILY,
            BadgeCode::HARDWORKING_FAMILY,
        ];

        $badges = [];
        foreach ($codes as $code) {
            $badge = $this->badgeRepository->findOneByCode($code->value);
            if ($badge === null) {
                throw new LogicException(sprintf('Badge with code "%s" is missing.', $code->value));
            }

            $badges[$code->value] = $badge;
        }

        return $badges;
    }

    private function hasRequiredBadges(): bool
    {
        $codes = [
            BadgeCode::HARDWORKING_MEMBER,
            BadgeCode::BALANCED_FAMILY,
            BadgeCode::HARDWORKING_FAMILY,
        ];

        foreach ($codes as $code) {
            if ($this->badgeRepository->findOneByCode($code->value) === null) {
                return false;
            }
        }

        return true;
    }
}
