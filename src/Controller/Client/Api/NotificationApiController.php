<?php

namespace App\Controller\Client\Api;

use App\Entity\Rappel;
use App\Repository\RappelRepository;
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

        $now = new \DateTimeImmutable();

        $qb = $rappelRepository->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.actif = true')
            ->andWhere('r.estLu = false')
            ->andWhere('r.scheduledAt <= :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('r.scheduledAt', 'DESC');

        $rappels = $qb->getQuery()->getResult();

        $data = [];
        foreach ($rappels as $rappel) {
            $event = $rappel->getEvenement();
            $title = $event ? $event->getTitre() : 'Événement';
            $offset = $rappel->getOffsetMinutes();
            $message = $offset > 0
                ? sprintf('Rappel : %s (dans %d min)', $title, $offset)
                : sprintf('Rappel : %s', $title);

            $data[] = [
                'id' => $rappel->getId(),
                'message' => $message,
                'scheduledAt' => $rappel->getScheduledAt()?->format('c'),
                'eventId' => $event?->getId(),
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
