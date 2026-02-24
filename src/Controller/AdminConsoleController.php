<?php

namespace App\Controller;

use App\Entity\AccountNotification;
use App\Entity\AuditTrail;
use App\Entity\Badge;
use App\Entity\Family;
use App\Entity\FamilyBadge;
use App\Entity\FamilyInvitation;
use App\Entity\FamilyMembership;
use App\Enum\BadgeScope;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/console', name: 'portal_admin_console_')]
final class AdminConsoleController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->redirectToRoute('portal_admin_console_families');
    }

    #[Route('/families', name: 'families', methods: ['GET'])]
    public function families(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $families = $entityManager->getRepository(Family::class)->createQueryBuilder('f')
            ->addSelect('creator')
            ->leftJoin('f.createdBy', 'creator')
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $invitations = $entityManager->getRepository(FamilyInvitation::class)->createQueryBuilder('i')
            ->addSelect('f', 'u')
            ->leftJoin('i.family', 'f')
            ->leftJoin('i.createdBy', 'u')
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $memberships = $entityManager->getRepository(FamilyMembership::class)->createQueryBuilder('m')
            ->addSelect('f', 'u')
            ->leftJoin('m.family', 'f')
            ->leftJoin('m.user', 'u')
            ->orderBy('m.joinedAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->render('ui_portal/admin/console/families.html.twig', [
            'active_menu' => 'admin-families',
            'consoleSection' => 'families',
            'families' => $families,
            'invitations' => $invitations,
            'memberships' => $memberships,
        ]);
    }

    #[Route('/badges', name: 'badges', methods: ['GET'])]
    public function badges(EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $badges = $entityManager->getRepository(Badge::class)->createQueryBuilder('b')
            ->orderBy('b.requiredPoints', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $badgeSummaries = [];
        foreach ($badges as $badge) {
            $scope = $badge->getScope();
            if ($scope === null) {
                continue;
            }

            $holders = $scope === BadgeScope::USER
                ? $userRepository->countUsersWithBadge($badge)
                : (int) $entityManager->getRepository(FamilyBadge::class)->count(['badge' => $badge]);

            $badgeSummaries[] = [
                'badge' => $badge,
                'scope' => $scope,
                'holders' => $holders,
            ];
        }

        $familyBadges = $entityManager->getRepository(FamilyBadge::class)->createQueryBuilder('fb')
            ->addSelect('f', 'b')
            ->leftJoin('fb.family', 'f')
            ->leftJoin('fb.badge', 'b')
            ->orderBy('fb.awardedAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->render('ui_portal/admin/console/badges.html.twig', [
            'active_menu' => 'admin-badges',
            'consoleSection' => 'badges',
            'badgeSummaries' => $badgeSummaries,
            'familyBadges' => $familyBadges,
        ]);
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $statusSummary = [
            'pending' => (int) $entityManager->getRepository(AccountNotification::class)->count(['status' => 'PENDING']),
            'sent' => (int) $entityManager->getRepository(AccountNotification::class)->count(['status' => 'SENT']),
            'failed' => (int) $entityManager->getRepository(AccountNotification::class)->count(['status' => 'FAILED']),
            'skipped' => (int) $entityManager->getRepository(AccountNotification::class)->count(['status' => 'SKIPPED']),
        ];

        $notificationRecords = $entityManager->getRepository(AccountNotification::class)->createQueryBuilder('n')
            ->addSelect('u')
            ->leftJoin('n.user', 'u')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(25)
            ->getQuery()
            ->getResult();

        $auditRecords = $entityManager->getRepository(AuditTrail::class)->createQueryBuilder('a')
            ->addSelect('u', 'f')
            ->leftJoin('a.user', 'u')
            ->leftJoin('a.family', 'f')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(25)
            ->getQuery()
            ->getResult();

        $auditLast24h = (int) $entityManager->getRepository(AuditTrail::class)->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.createdAt >= :cutoff')
            ->setParameter('cutoff', new DateTimeImmutable('-24 hours'))
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('ui_portal/admin/console/notifications.html.twig', [
            'active_menu' => 'admin-notifications',
            'consoleSection' => 'notifications',
            'statusSummary' => $statusSummary,
            'notificationRecords' => $notificationRecords,
            'auditRecords' => $auditRecords,
            'auditLast24h' => $auditLast24h,
        ]);
    }
}
