<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Message\SMS\RecalculateActivityPatternMessage;
use App\Service\AuditTrailService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

final class LoginActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly AuditTrailService $auditTrailService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (!$user instanceof User) {
            return;
        }

        $now = new DateTimeImmutable();
        $user->setLastLogin(new DateTime());
        $user->setUpdatedAt($now);

        $request = $this->requestStack->getCurrentRequest();
        $payload = [
            'ip' => $request?->getClientIp(),
            'userAgent' => $request?->headers->get('User-Agent'),
        ];

        $this->entityManager->flush();
        $this->auditTrailService->record($user, 'user.login', $payload, $user->getFamily());

        // Dispatch activity pattern recalculation
        $message = new RecalculateActivityPatternMessage($user->getId());
        $this->messageBus->dispatch($message);
    }
}
