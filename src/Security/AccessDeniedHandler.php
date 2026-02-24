<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private readonly RouterInterface $router, private readonly Security $security)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): RedirectResponse 
    {
        if (!$this->security->isGranted('ROLE_USER')) {
            return new RedirectResponse($this->router->generate('portal_auth_login'));
        }

        return new RedirectResponse($this->router->generate('portal_account'));
    }
}
