<?php

namespace App\Controller\ModuleEvenement\Client;

use App\Repository\NotificationRepository;
use App\Repository\RappelRepository;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;
use App\Entity\Family;

#[Route('/portal/evenements/notifications')]
class NotificationsController extends AbstractController
{
    #[Route('', name: 'app_notifications', methods: ['GET'])]
    public function index(
        RappelRepository $rappelRepository,
        NotificationRepository $notificationRepository,
        Request $request,
        ActiveFamilyResolver $familyResolver
    ): Response
    {
        $user = $this->getUser();
        $family = $this->resolveFamily($familyResolver);

        $rappelRepository->cleanupOrphanedAndPast();
        $filter = $request->query->get('filter', 'all');
        $now = new \DateTimeImmutable();
        $qb = $rappelRepository->createQueryBuilder('r')
            ->innerJoin('r.evenement', 'e')
            ->andWhere('r.user = :user')
            ->andWhere('e.family = :family')
            ->setParameter('user', $user)
            ->setParameter('family', $family)
            ->andWhere('e.dateFin >= :now')
            ->setParameter('now', $now)
            ->orderBy('r.scheduledAt', 'DESC');

        if ($filter === 'unread') {
            $qb->andWhere('r.estLu = false');
        }

        $documentNotifications = $notificationRepository->findAllForRecipientInFamily($user, $family);
        if ($filter === 'unread') {
            $documentNotifications = array_values(array_filter(
                $documentNotifications,
                static fn($notification) => !$notification->isRead()
            ));
        }

        return $this->render('app/notifications/index.html.twig', [
            'rows' => array_map(static function ($rappel): array {
                $eventTitle = null;
                try {
                    $event = $rappel->getEvenement();
                    if ($event !== null) {
                        $eventTitle = $event->getTitre();
                    }
                } catch (EntityNotFoundException $exception) {
                    $eventTitle = null;
                }

                return [
                    'rappel' => $rappel,
                    'eventTitle' => $eventTitle,
                ];
            }, $qb->getQuery()->getResult()),
            'document_notifications' => $documentNotifications,
            'filter' => $filter,
        ]);
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
