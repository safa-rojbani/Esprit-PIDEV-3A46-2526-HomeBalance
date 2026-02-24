<?php

namespace App\Controller;

use App\Repository\AccountNotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/notifications', name: 'portal_admin_notifications_')]
final class AdminNotificationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, AccountNotificationRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $status = $request->query->get('status');
        $status = is_string($status) && $status !== '' ? $status : null;

        return $this->render('ui_portal/admin/notifications/index.html.twig', [
            'active_menu' => 'admin-notifications',
            'status' => $status,
            'notifications' => $repository->findRecent($status),
        ]);
    }
}
