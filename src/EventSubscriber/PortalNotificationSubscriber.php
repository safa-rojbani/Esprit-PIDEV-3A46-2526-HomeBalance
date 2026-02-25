<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\ActiveFamilyResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

final class PortalNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $familyResolver,
        private readonly NotificationRepository $notificationRepository,
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

        $notifications = $this->notificationRepository->findAllForRecipientInFamily($user, $family);
        $unreadCount = 0;
        $latestUnread = null;

        foreach ($notifications as $notification) {
            if ($notification->isRead()) {
                continue;
            }

            ++$unreadCount;
            if ($latestUnread === null) {
                $latestUnread = $notification;
            }
        }

        $request->attributes->set('portal_notification_unread_count', $unreadCount);
        $request->attributes->set('portal_notification_latest_unread', $latestUnread);
        $request->attributes->set('portal_notifications', $notifications);
    }
}
