<?php

namespace App\Controller;

use App\Entity\AccountNotification;
use App\Form\Admin\AccountNotificationAdminType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/ums/notifications', name: 'portal_admin_account_notifications_')]
final class AdminAccountNotificationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'createdAt');
        $direction = strtoupper((string) $request->query->get('dir', 'DESC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';
        $allowedSorts = [
            'createdAt' => 'n.createdAt',
            'status' => 'n.status',
            'channel' => 'n.channel',
        ];
        $sortField = $allowedSorts[$sort] ?? $allowedSorts['createdAt'];

        $qb = $entityManager->getRepository(AccountNotification::class)->createQueryBuilder('n')
            ->addSelect('u')
            ->leftJoin('n.user', 'u');

        if ($query !== '') {
            $qb->andWhere('LOWER(n.key) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(u.username) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $records = $qb
            ->orderBy($sortField, $direction)
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $this->render('ui_portal/admin/ums/account_notifications/index.html.twig', [
            'active_menu' => 'admin-account-notifications',
            'records' => $records,
            'filters' => [
                'query' => $query,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $record = new AccountNotification();
        $form = $this->createForm(AccountNotificationAdminType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($record);
            $entityManager->flush();

            $this->addFlash('success', 'Notification record created.');

            return $this->redirectToRoute('portal_admin_account_notifications_index');
        }

        return $this->render('ui_portal/admin/ums/account_notifications/form.html.twig', [
            'active_menu' => 'admin-account-notifications',
            'title' => 'Create notification',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(AccountNotification $record, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(AccountNotificationAdminType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Notification updated.');

            return $this->redirectToRoute('portal_admin_account_notifications_index');
        }

        return $this->render('ui_portal/admin/ums/account_notifications/form.html.twig', [
            'active_menu' => 'admin-account-notifications',
            'title' => 'Edit notification',
            'form' => $form->createView(),
            'formErrors' => $this->collectFormErrors($form),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(AccountNotification $record, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_account_notification_' . $record->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($record);
        $entityManager->flush();

        $this->addFlash('success', 'Notification deleted.');

        return $this->redirectToRoute('portal_admin_account_notifications_index');
    }

    /**
     * @return list<string>
     */
    private function collectFormErrors(FormInterface $form): array
    {
        if (!$form->isSubmitted() || $form->isValid()) {
            return [];
        }

        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $messages[] = $error->getMessage();
        }

        return array_values(array_unique($messages));
    }
}
