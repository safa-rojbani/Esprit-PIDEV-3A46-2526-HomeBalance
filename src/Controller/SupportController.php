<?php

namespace App\Controller;

use App\Entity\SupportMessage;
use App\Entity\SupportTicket;
use App\Entity\User;
use App\Form\SupportTicketType;
use App\Repository\SupportTicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/support', name: 'portal_support_')]
final class SupportController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SupportTicketRepository $ticketRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Admins go to their dedicated support management view
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('portal_admin_support_index');
        }

        /** @var User $user */
        $user = $this->getUser();
        $tickets = $ticketRepository->findByUser($user);

        return $this->render('ui_portal/support/index.html.twig', [
            'active_menu' => 'support',
            'tickets' => $tickets,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $ticket = new SupportTicket();
        $form = $this->createForm(SupportTicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticket->setUser($user);
            $entityManager->persist($ticket);
            $entityManager->flush();

            $this->addFlash('success', 'Your support ticket has been created successfully.');

            return $this->redirectToRoute('portal_support_show', ['id' => $ticket->getId()]);
        }

        return $this->render('ui_portal/support/new.html.twig', [
            'active_menu' => 'support',
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(SupportTicket $ticket, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        // Security: users can only view their own tickets
        if ($ticket->getUser() !== $user) {
            throw $this->createAccessDeniedException('You cannot view this ticket.');
        }

        // Handle reply
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('support_reply_' . $ticket->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $content = trim((string) $request->request->get('reply_content', ''));

            if ($content !== '') {
                $message = new SupportMessage();
                $message->setTicket($ticket);
                $message->setAuthor($user);
                $message->setContent($content);

                $entityManager->persist($message);
                $entityManager->flush();

                $this->addFlash('success', 'Your reply has been sent.');

                return $this->redirectToRoute('portal_support_show', ['id' => $ticket->getId()]);
            }

            $this->addFlash('error', 'Reply content cannot be empty.');
        }

        return $this->render('ui_portal/support/show.html.twig', [
            'active_menu' => 'support',
            'ticket' => $ticket,
        ]);
    }
}
