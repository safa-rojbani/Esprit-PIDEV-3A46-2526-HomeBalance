<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Service\ActiveFamilyResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/notifications', name: 'portal_notifications_')]
final class PortalNotificationController extends AbstractController
{
    #[Route('/{id}/read', name: 'read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markRead(
        Notification $notification,
        Request $request,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid(
            'portal_notification_read_' . $notification->getId(),
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $family = $familyResolver->resolveForUser($user);
        if ($family === null) {
            throw $this->createAccessDeniedException();
        }

        if (
            $notification->getRecipient()?->getId() !== $user->getId()
            || $notification->getFamily()?->getId() !== $family->getId()
        ) {
            throw $this->createAccessDeniedException();
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $notification->setReadAt(new \DateTimeImmutable());
            $entityManager->flush();
        }

        $redirect = (string) $request->request->get('_redirect', '');
        if (str_starts_with($redirect, '/portal')) {
            return $this->redirect($redirect);
        }

        return $this->redirectToRoute('portal_dashboard');
    }
}
