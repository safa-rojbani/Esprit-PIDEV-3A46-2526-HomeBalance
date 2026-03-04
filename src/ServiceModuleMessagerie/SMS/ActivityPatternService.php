<?php

namespace App\ServiceModuleMessagerie\SMS;

use App\Entity\User;
use App\Entity\UserActivityPattern;
use App\Repository\AuditTrailRepository;
use App\Repository\UserActivityPatternRepository;
use App\Service\AuditTrailService;
use Doctrine\ORM\EntityManagerInterface;

class ActivityPatternService
{
    private const DAYS_TO_ANALYZE = 30;
    private const TOP_PEAK_HOURS = 3;

    public function __construct(
        private readonly AuditTrailRepository $auditTrailRepository,
        private readonly UserActivityPatternRepository $activityPatternRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    /**
     * Calculate peak hours based on user's activity patterns.
     *
     * @return array<int, int> Array of 24 integers (index = hour, value = activity count)
     */
    public function calculatePeakHours(User $user): array
    {
        // Get user's timezone
        $timezone = $user->getTimeZone() ?? 'UTC';
        
        // Get cutoff date
        $days = self::DAYS_TO_ANALYZE;
        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        
        // Query audit trails for this user
        $auditTrails = $this->auditTrailRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.createdAt >= :cutoff')
            ->setParameter('user', $user)
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();
        
        // Initialize hour counts
        $hourCounts = array_fill(0, 24, 0);
        
        // Count activities per hour
        foreach ($auditTrails as $trail) {
            $createdAt = $trail->getCreatedAt();
            if ($createdAt) {
                $dateTime = $createdAt->setTimezone(new \DateTimeZone($timezone));
                $hour = (int) $dateTime->format('H');
                $hourCounts[$hour]++;
            }
        }
        
        // Get top peak hours
        $peakHours = $this->getTopPeakHours($hourCounts);
        
        // Persist pattern
        $pattern = $this->activityPatternRepository->findOrCreateForUser($user);
        $pattern->setPeakHours($peakHours);
        $pattern->setLastCalculatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
        
        // Record audit trail
        $this->auditTrailService->record(
            $user,
            'sms.pattern.recalculated',
            [
                'userId' => $user->getId(),
                'peakHours' => $peakHours,
            ],
            $user->getFamily()
        );
        
        return $hourCounts;
    }

    /**
     * Get next optimal send time based on user's activity pattern.
     */
    public function getNextOptimalSendTime(User $user): \DateTimeImmutable
    {
        $pattern = $this->activityPatternRepository->findOneBy(['user' => $user]);
        
        // If no pattern exists, return now
        if (!$pattern || empty($pattern->getPeakHours())) {
            return new \DateTimeImmutable();
        }
        
        $peakHours = $pattern->getPeakHours();
        $timezone = $user->getTimeZone() ?? 'UTC';
        
        // Get current time in user's timezone
        $now = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        $currentHour = (int) $now->format('H');
        
        // Find next peak hour within next 12 hours
        $optimalTime = null;
        
        foreach ($peakHours as $peakHour) {
            if ($peakHour > $currentHour && $peakHour <= $currentHour + 12) {
                $candidateTime = $now->setTime($peakHour, 0, 0);
                
                // Skip if in quiet hours
                if (!$this->isInQuietHours($user, $candidateTime)) {
                    $optimalTime = $candidateTime;
                    break;
                }
            }
        }
        
        // If no optimal time found in next 12 hours, return now (send immediately)
        if (!$optimalTime) {
            return new \DateTimeImmutable();
        }
        
        return $optimalTime;
    }

    /**
     * Get top N peak hours from hour counts.
     *
     * @param array<int, int> $hourCounts
     * @return list<int>
     */
    private function getTopPeakHours(array $hourCounts): array
    {
        // Sort hours by count descending
        $sorted = $hourCounts;
        arsort($sorted);
        
        // Get top N keys
        $topKeys = array_keys($sorted, 0, $this::TOP_PEAK_HOURS);
        
        // Return sorted list of peak hours
        sort($topKeys);
        
        return array_slice($topKeys, 0, $this::TOP_PEAK_HOURS);
    }

    /**
     * Check if a specific time is within user's quiet hours.
     */
    private function isInQuietHours(User $user, \DateTimeImmutable $time): bool
    {
        $preferences = $user->getPreferences() ?? [];
        $quietHours = $preferences['communication']['quietHours'] ?? [];
        
        $quietStart = $quietHours['start'] ?? null;
        $quietEnd = $quietHours['end'] ?? null;
        
        if (!$quietStart || !$quietEnd) {
            return false;
        }
        
        $timeValue = (int) $time->format('Hi');
        $startValue = (int) str_replace(':', '', $quietStart);
        $endValue = (int) str_replace(':', '', $quietEnd);
        
        // Handle overnight quiet hours
        if ($startValue > $endValue) {
            return $timeValue >= $startValue || $timeValue <= $endValue;
        }
        
        return $timeValue >= $startValue && $timeValue <= $endValue;
    }
}
