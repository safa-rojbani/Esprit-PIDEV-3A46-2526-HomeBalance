<?php

namespace App\Controller;

use App\Enum\BadgeScope;
use App\Repository\BadgeRepository;
use App\Repository\FamilyBadgeRepository;
use App\Repository\UserRepository;
use App\Service\BadgeAwardingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/gamification', name: 'portal_admin_gamification_')]
final class AdminGamificationController extends AbstractController
{
    #[Route('/badges', name: 'badges', methods: ['GET'])]
    public function badges(
        BadgeRepository $badgeRepository,
        UserRepository $userRepository,
        FamilyBadgeRepository $familyBadgeRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $badgeSummaries = [];
        foreach ($badgeRepository->findAll() as $badge) {
            $scope = $badge->getScope();
            if ($scope === null) {
                continue;
            }

            $holders = $scope === BadgeScope::USER
                ? $userRepository->countUsersWithBadge($badge)
                : $familyBadgeRepository->countByBadge($badge);

            $badgeSummaries[] = [
                'badge' => $badge,
                'holders' => $holders,
                'scope' => $scope,
            ];
        }

        return $this->render('ui_portal/admin/gamification/badges.html.twig', [
            'active_menu' => 'admin-badges',
            'badges' => $badgeSummaries,
        ]);
    }

    #[Route('/badges/award', name: 'award', methods: ['POST'])]
    public function award(
        Request $request,
        BadgeAwardingService $badgeAwardingService,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('award_badges', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $result = $badgeAwardingService->awardAll();
        $this->addFlash('success', sprintf(
            'Badges recalculated. %d user updates, %d family updates.',
            $result['userBadgesChanged'],
            $result['familyBadgesChanged']
        ));

        return $this->redirectToRoute('portal_admin_gamification_badges');
    }
}
