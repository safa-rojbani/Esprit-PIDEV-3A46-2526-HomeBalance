<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ActiveFamilyResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

final class FamilyGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $familyResolver,
        private readonly RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/portal')) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (!is_string($route) || $route === '') {
            return;
        }

        if ($this->isAllowedRoute($route)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Admins never need a family — skip the guard entirely
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        if ($this->familyResolver->hasActiveFamily($user)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('portal_onboarding_family')));
    }

    private function isAllowedRoute(string $route): bool
    {
        if (str_starts_with($route, 'portal_auth_')) {
            return true;
        }

        if (str_starts_with($route, 'portal_onboarding_')) {
            return true;
        }

        if (str_starts_with($route, 'portal_admin_')) {
            return true;
        }

        return false;
    }
}
