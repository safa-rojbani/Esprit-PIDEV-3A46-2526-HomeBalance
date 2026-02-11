<?php

namespace App\Controller\ModuleEvenement\Client\Api;

use App\Entity\Rappel;
use App\Repository\RappelRepository;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/notifications')]
class NotificationApiController extends AbstractController
{
    #[Route('/pending', name: 'app_notifications_pending', methods: ['GET'])]
    public function pending(RappelRepository $rappelRepository): JsonResponse
    {
        $user = $this->getUser();
        if ($user === null) {
            return $this->json([]);
        }

        $rappelRepository->cleanupOrphanedAndPast();
        $now = new \DateTimeImmutable();

        $qb = $rappelRepository->createQueryBuilder('r')
            ->innerJoin('r.evenement', 'e')
            ->andWhere('r.user = :user')
            ->andWhere('(r.actif = true OR r.actif IS NULL)')
            ->andWhere('(r.estLu = false OR r.estLu IS NULL)')
            ->andWhere('r.scheduledAt <= :now')
            ->andWhere('e.dateFin >= :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('r.scheduledAt', 'DESC');

        $rappels = $qb->getQuery()->getResult();

        $data = [];
        foreach ($rappels as $rappel) {
            $event = null;
            $eventTitle = null;
            $eventId = null;
            try {
                $event = $rappel->getEvenement();
                if ($event !== null) {
                    $eventTitle = $event->getTitre();
                    $eventId = $event->getId();
                }
            } catch (EntityNotFoundException $exception) {
                $event = null;
            }
            $title = $eventTitle ?? 'Evenement';
            $offset = $rappel->getOffsetMinutes();
            $message = $offset > 0
                ? sprintf('Rappel : %s (dans %d min)', $title, $offset)
                : sprintf('Rappel : %s', $title);

            $data[] = [
                'id' => $rappel->getId(),
                'message' => $message,
                'scheduledAt' => $rappel->getScheduledAt()?->format('c'),
                'eventId' => $eventId,
                'eventTitle' => $title,
                'canal' => $rappel->getCanal(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}/read', name: 'app_notification_read', methods: ['POST'])]
    public function markRead(int $id, RappelRepository $rappelRepository, EntityManagerInterface $em, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if ($user === null) {
            return $this->json(['ok' => false], 401);
        }

        $rappel = $rappelRepository->find($id);
        if (!$rappel instanceof Rappel || $rappel->getUser() !== $user) {
            return $this->json(['ok' => false], 404);
        }

        $rappel->setEstLu(true);
        $rappel->setReadAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['ok' => true]);
    }
}
