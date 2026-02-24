<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\UserStatus;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class UserStatusSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/portal')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $status = $user->getStatus();
        if ($status === null || $status === UserStatus::ACTIVE) {
            return;
        }

        $message = $status === UserStatus::DELETED
            ? 'This account has been deleted.'
            : 'This account is temporarily suspended.';

        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->getFlashBag()->add('error', $message);
            $session->remove('_security_main');
        }

        $this->tokenStorage->setToken(null);

        $route = $request->attributes->get('_route');
        if (is_string($route) && str_starts_with($route, 'portal_auth_')) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('portal_auth_login')));
    }
}
