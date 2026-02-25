<?php

namespace App\Controller\ModuleDocuments\FrontOffice\API;

use App\Entity\Family;
use App\Entity\User;
use App\Enum\DocumentActivityEvent;
use App\Repository\DocumentActivityLogRepository;
use App\Service\ActiveFamilyResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/documents/api/insights')]
final class DocumentInsightsController extends AbstractController
{
    #[Route('/overview', name: 'app_document_insights_overview', methods: ['GET'])]
    public function overview(
        Request $request,
        ActiveFamilyResolver $familyResolver,
        DocumentActivityLogRepository $activityLogRepository
    ): JsonResponse {
        $family = $this->resolveFamily($familyResolver);
        $days = $this->resolveRangeDays((string) $request->query->get('range', '30d'));
        [$from, $to] = $this->buildRangeWindow($days);

        $overview = $activityLogRepository->getOverview($family, $from, $to);
        $dailyShares = $activityLogRepository->getDailyShares($family, $from, $to);

        return $this->json([
            'ok' => true,
            'range_days' => $days,
            'from' => $from->format(\DATE_ATOM),
            'to' => $to->format(\DATE_ATOM),
            'overview' => $overview,
            'daily_shares' => $dailyShares,
        ]);
    }

    #[Route('/top-documents', name: 'app_document_insights_top_documents', methods: ['GET'])]
    public function topDocuments(
        Request $request,
        ActiveFamilyResolver $familyResolver,
        DocumentActivityLogRepository $activityLogRepository
    ): JsonResponse {
        $family = $this->resolveFamily($familyResolver);
        $days = $this->resolveRangeDays((string) $request->query->get('range', '30d'));
        [$from, $to] = $this->buildRangeWindow($days);

        $limit = max(1, min(20, $request->query->getInt('limit', 5)));
        $topDocuments = $activityLogRepository->getTopSharedDocuments($family, $from, $to, $limit);

        return $this->json([
            'ok' => true,
            'range_days' => $days,
            'limit' => $limit,
            'from' => $from->format(\DATE_ATOM),
            'to' => $to->format(\DATE_ATOM),
            'items' => $topDocuments,
        ]);
    }

    #[Route('/activity-heatmap', name: 'app_document_insights_activity_heatmap', methods: ['GET'])]
    public function activityHeatmap(
        Request $request,
        ActiveFamilyResolver $familyResolver,
        DocumentActivityLogRepository $activityLogRepository
    ): JsonResponse {
        $family = $this->resolveFamily($familyResolver);
        $days = $this->resolveRangeDays((string) $request->query->get('range', '30d'));
        [$from, $to] = $this->buildRangeWindow($days);

        $hours = $activityLogRepository->getHourlyActivity($family, $from, $to);
        $items = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $items[] = [
                'hour' => $hour,
                'count' => (int) ($hours[$hour] ?? 0),
            ];
        }

        return $this->json([
            'ok' => true,
            'range_days' => $days,
            'from' => $from->format(\DATE_ATOM),
            'to' => $to->format(\DATE_ATOM),
            'items' => $items,
        ]);
    }

    #[Route('/alerts', name: 'app_document_insights_alerts', methods: ['GET'])]
    public function alerts(
        ActiveFamilyResolver $familyResolver,
        DocumentActivityLogRepository $activityLogRepository
    ): JsonResponse {
        $family = $this->resolveFamily($familyResolver);
        $now = new \DateTimeImmutable();
        $last24hFrom = $now->modify('-24 hours');
        $previousWeekFrom = $now->modify('-8 days');
        $previousWeekTo = $now->modify('-24 hours');

        $sharesLast24h = $activityLogRepository->countEvent(
            $family,
            DocumentActivityEvent::DOCUMENT_SHARED,
            $last24hFrom,
            $now
        );
        $sharesPreviousWeek = $activityLogRepository->countEvent(
            $family,
            DocumentActivityEvent::DOCUMENT_SHARED,
            $previousWeekFrom,
            $previousWeekTo
        );
        $blocksLast24h = $activityLogRepository->countEvent(
            $family,
            DocumentActivityEvent::DOCUMENT_SHARE_BLOCKED,
            $last24hFrom,
            $now
        );

        $averagePreviousDay = $sharesPreviousWeek / 7;
        $spikeThreshold = (int) max(10, ceil($averagePreviousDay * 2));

        $alerts = [];
        if ($sharesLast24h >= $spikeThreshold) {
            $alerts[] = [
                'type' => 'share_spike',
                'severity' => 'warning',
                'message' => sprintf(
                    'Pic de partages detecte: %d partages en 24h (seuil %d).',
                    $sharesLast24h,
                    $spikeThreshold
                ),
                'metrics' => [
                    'shares_last_24h' => $sharesLast24h,
                    'average_previous_day' => round($averagePreviousDay, 2),
                    'threshold' => $spikeThreshold,
                ],
            ];
        }

        if ($blocksLast24h >= 3) {
            $alerts[] = [
                'type' => 'share_blocks_high',
                'severity' => 'info',
                'message' => sprintf(
                    'Activite bloquee elevee: %d blocages de partage sur les 24h.',
                    $blocksLast24h
                ),
                'metrics' => [
                    'share_blocks_last_24h' => $blocksLast24h,
                ],
            ];
        }

        return $this->json([
            'ok' => true,
            'window' => [
                'from' => $last24hFrom->format(\DATE_ATOM),
                'to' => $now->format(\DATE_ATOM),
            ],
            'alerts' => $alerts,
        ]);
    }

    /**
     * @return array{\DateTimeImmutable,\DateTimeImmutable}
     */
    private function buildRangeWindow(int $days): array
    {
        $to = new \DateTimeImmutable();
        $from = $to->modify(sprintf('-%d days', $days));

        return [$from, $to];
    }

    private function resolveRangeDays(string $range): int
    {
        return match (strtolower(trim($range))) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };
    }

    private function resolveFamily(ActiveFamilyResolver $familyResolver): Family
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $family = $familyResolver->resolveForUser($user);
        if ($family === null) {
            throw $this->createAccessDeniedException();
        }

        return $family;
    }
}

