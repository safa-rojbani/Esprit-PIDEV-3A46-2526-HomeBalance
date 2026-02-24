<?php

namespace App\Controller;

use App\Repository\AccountNotificationRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/notifications', name: 'portal_admin_notifications_')]
final class AdminNotificationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, AccountNotificationRepository $repository, PaginatorInterface $paginator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $status = $request->query->get('status');
        $status = is_string($status) && $status !== '' ? $status : null;
        $page = max(1, $request->query->getInt('page', 1));

        $notificationsPagination = $paginator->paginate(
            $repository->createRecentQueryBuilder($status),
            $page,
            20,
            [
                'distinct' => true,
                'sortFieldParameterName' => '_knp_sort',
                'sortDirectionParameterName' => '_knp_dir',
            ],
        );

        $total = (int) $notificationsPagination->getTotalItemCount();
        $first = $total > 0 ? (($page - 1) * 20) + 1 : 0;
        $last = min($page * 20, $total);

        return $this->render('ui_portal/admin/notifications/index.html.twig', [
            'active_menu' => 'admin-notifications',
            'status' => $status,
            'notifications' => $notificationsPagination,
            'paginationMeta' => [
                'first' => $first,
                'last' => $last,
                'total' => $total,
            ],
        ]);
    }
}
