<?php

namespace App\Controller\Client;

use App\Repository\RappelRepository;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/notifications')]
class NotificationsController extends AbstractController
{
    #[Route('', name: 'app_notifications', methods: ['GET'])]
    public function index(RappelRepository $rappelRepository, Request $request): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            return $this->render('app/notifications/index.html.twig', [
                'rappels' => [],
                'filter' => 'all',
            ]);
        }

        $rappelRepository->cleanupOrphanedAndPast();
        $filter = $request->query->get('filter', 'all');
        $now = new \DateTimeImmutable();
        $qb = $rappelRepository->createQueryBuilder('r')
            ->innerJoin('r.evenement', 'e')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->andWhere('e.dateFin >= :now')
            ->setParameter('now', $now)
            ->orderBy('r.scheduledAt', 'DESC');

        if ($filter === 'unread') {
            $qb->andWhere('r.estLu = false');
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
            'filter' => $filter,
        ]);
    }
}
