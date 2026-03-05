<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\RappelRepository;
use Doctrine\ORM\EntityNotFoundException;
use App\Service\ActiveFamilyResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

final class PortalNotificationSubscriber implements EventSubscriberInterface
{
    private const NAVBAR_NOTIFICATION_LIMIT = 20;

    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $familyResolver,
        private readonly NotificationRepository $notificationRepository,
        private readonly RappelRepository $rappelRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/portal')) {
            return;
        }

        $request->attributes->set('portal_notification_unread_count', 0);
        $request->attributes->set('portal_notification_latest_unread', null);
        $request->attributes->set('portal_notifications', []);

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $family = $this->familyResolver->resolveForUser($user);
        if ($family === null) {
            return;
        }

        if ($family->getId() === null) {
            return;
        }

        $notifications = $this->notificationRepository
            ->findAllForRecipientInFamily($user, $family, self::NAVBAR_NOTIFICATION_LIMIT);
        $unreadCount = $this->notificationRepository->countUnreadForRecipientInFamily($user, $family);
        $latestUnread = null;
        foreach ($notifications as $notification) {
            if ($notification->isRead()) {
                break;
            }

            $latestUnread = $notification;
            break;
        }

        $now = new \DateTimeImmutable();
        $rappelBaseQb = $this->rappelRepository->createQueryBuilder('r')
            ->innerJoin('r.evenement', 'e')
            ->andWhere('r.user = :user')
            ->andWhere('e.family = :family')
            ->andWhere('e.dateFin >= :now')
            ->setParameter('user', $user)
            ->setParameter('family', $family)
            ->setParameter('now', $now);

        $rappelUnreadCount = (int) (clone $rappelBaseQb)
            ->select('COUNT(r.id)')
            ->andWhere('r.estLu = false')
            ->getQuery()
            ->getSingleScalarResult();

        $rappelIdRows = (clone $rappelBaseQb)
            ->select('r.id AS id')
            ->orderBy('r.scheduledAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getScalarResult();

        $rappelIds = array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rappelIdRows
        );
        $rappelIds = array_values(array_filter($rappelIds, static fn (int $id): bool => $id > 0));

        $rappels = [];
        if ($rappelIds !== []) {
            /** @var array<int, \App\Entity\Rappel> $rappelsById */
            $rappelsById = $this->rappelRepository->createQueryBuilder('r', 'r.id')
                ->addSelect('e')
                ->innerJoin('r.evenement', 'e')
                ->andWhere('r.id IN (:ids)')
                ->setParameter('ids', $rappelIds)
                ->getQuery()
                ->getResult();

            foreach ($rappelIds as $id) {
                if (isset($rappelsById[$id])) {
                    $rappels[] = $rappelsById[$id];
                }
            }
        }

        $rappelRows = array_map(static function ($rappel): array {
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
        }, $rappels);

        $request->attributes->set('portal_document_notification_unread_count', $unreadCount);
        $request->attributes->set('portal_event_notification_unread_count', $rappelUnreadCount);
        $request->attributes->set('portal_notification_unread_count', $unreadCount + $rappelUnreadCount);
        $request->attributes->set('portal_notification_latest_unread', $latestUnread);
        $request->attributes->set('portal_notifications', $notifications);
        $request->attributes->set('portal_document_notifications', $notifications);
        $request->attributes->set('portal_event_notifications', $rappelRows);
    }
}
