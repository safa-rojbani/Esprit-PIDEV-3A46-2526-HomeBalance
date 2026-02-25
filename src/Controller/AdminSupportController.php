<?php

namespace App\Controller;

use App\Entity\SupportMessage;
use App\Entity\SupportTicket;
use App\Entity\User;
use App\Enum\StatusSupportTicket;
use App\Repository\SupportTicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/support', name: 'portal_admin_support_')]
final class AdminSupportController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SupportTicketRepository $ticketRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $statusFilter = $request->query->get('status');
        $tickets = $ticketRepository->findAllOrdered($statusFilter);

        return $this->render('ui_portal/admin/support/index.html.twig', [
            'active_menu' => 'admin-support',
            'tickets' => $tickets,
            'filters' => [
                'status' => $statusFilter,
                'statusChoices' => [
                    '' => 'All',
                    StatusSupportTicket::OPEN->value => 'Open',
                    StatusSupportTicket::IN_PROGRESS->value => 'In Progress',
                    StatusSupportTicket::CLOSED->value => 'Closed',
                ],
            ],
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(SupportTicket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $admin */
        $admin = $this->getUser();

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action');

            // Handle status change
            if ($action === 'change_status') {
                if (!$this->isCsrfTokenValid('change_status_' . $ticket->getId(), (string) $request->request->get('_token'))) {
                    throw $this->createAccessDeniedException('Invalid CSRF token.');
                }

                $newStatus = (string) $request->request->get('status', '');
                $statusEnum = StatusSupportTicket::tryFrom($newStatus);

                if ($statusEnum !== null) {
                    $ticket->setStatus($statusEnum);
                    $entityManager->flush();

                    $this->addFlash('success', 'Ticket status updated to "' . ucfirst(str_replace('_', ' ', $statusEnum->value)) . '".');
                }

                return $this->redirectToRoute('portal_admin_support_show', ['id' => $ticket->getId()]);
            }

            // Handle reply
            if ($action === 'reply') {
                if (!$this->isCsrfTokenValid('admin_reply_' . $ticket->getId(), (string) $request->request->get('_token'))) {
                    throw $this->createAccessDeniedException('Invalid CSRF token.');
                }

                $content = trim((string) $request->request->get('reply_content', ''));

                if ($content !== '') {
                    $message = new SupportMessage();
                    $message->setTicket($ticket);
                    $message->setAuthor($admin);
                    $message->setContent($content);

                    $entityManager->persist($message);

                    // Auto-set to IN_PROGRESS if ticket was OPEN
                    if ($ticket->getStatus() === StatusSupportTicket::OPEN) {
                        $ticket->setStatus(StatusSupportTicket::IN_PROGRESS);
                    }

                    $entityManager->flush();

                    $this->addFlash('success', 'Reply sent successfully.');
                } else {
                    $this->addFlash('error', 'Reply content cannot be empty.');
                }

                return $this->redirectToRoute('portal_admin_support_show', ['id' => $ticket->getId()]);
            }
        }

        return $this->render('ui_portal/admin/support/show.html.twig', [
            'active_menu' => 'admin-support',
            'ticket' => $ticket,
            'statusChoices' => StatusSupportTicket::cases(),
        ]);
    }
}
