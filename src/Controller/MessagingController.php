<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\OnlineStatus;
use App\Enum\TypeConversation;
use App\Repository\ConversationParticipantRepository;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/messaging')]
class MessagingController extends AbstractController
{
    private function getActiveUser(SessionInterface $session, UserRepository $userRepository): ?User
    {
        $userId = $session->get('current_user_id');
        if (!$userId) {
            return null;
        }
        return $userRepository->find($userId);
    }

    #[Route('/select-user', name: 'messaging_select_user')]
    public function selectUser(
        Request $request,
        SessionInterface $session,
        UserRepository $userRepository
    ): Response {
        if ($request->isMethod('POST')) {
            $userId = $request->request->get('user_id');
            $session->set('current_user_id', $userId);
            return $this->redirectToRoute('messaging_index');
        }

        $users = $userRepository->findAll();

        return $this->render('messaging/select_user.html.twig', [
            'users' => $users,
            'currentUserId' => $session->get('current_user_id'),
        ]);
    }

    #[Route('/', name: 'messaging_index')]
    public function index(
        SessionInterface $session,
        UserRepository $userRepository,
        ConversationParticipantRepository $participantRepository
    ): Response {
        $activeUser = $this->getActiveUser($session, $userRepository);
        
        if (!$activeUser) {
            return $this->redirectToRoute('messaging_select_user');
        }

        // Get all conversations for the active user
        $participants = $participantRepository->findBy(['user' => $activeUser]);
        $conversations = array_map(fn($p) => $p->getConversation(), $participants);

        return $this->render('messaging/index.html.twig', [
            'activeUser' => $activeUser,
            'conversations' => $conversations,
            'onlineStatus' => $session->get('online_status', OnlineStatus::ONLINE->value),
        ]);
    }

    #[Route('/conversation/new', name: 'messaging_new', priority: 10)]
    public function new(
        Request $request,
        SessionInterface $session,
        UserRepository $userRepository,
        ConversationParticipantRepository $participantRepository,
        EntityManagerInterface $em
    ): Response {
        $activeUser = $this->getActiveUser($session, $userRepository);
        
        if (!$activeUser) {
            return $this->redirectToRoute('messaging_select_user');
        }

        if ($request->isMethod('POST')) {
            $type = $request->request->get('type');
            $participantIds = $request->request->all('participants');
            $conversationName = $request->request->get('conversation_name');

            // Validation
            if (empty($participantIds)) {
                $this->addFlash('error', 'Please select at least one participant');
                return $this->redirectToRoute('messaging_new');
            }

            if ($type === TypeConversation::GROUP->value && empty($conversationName)) {
                $this->addFlash('error', 'Group conversations require a name');
                return $this->redirectToRoute('messaging_new');
            }

            // Create conversation
            $conversation = new Conversation();
            $conversation->setType(TypeConversation::from($type));
            $conversation->setFamily($activeUser->getFamily());
            $conversation->setCreatedBy($activeUser);
            $conversation->setCreatedAt(new \DateTimeImmutable());

            // Set conversation name
            if ($type === TypeConversation::GROUP->value) {
                $conversation->setConversationName($conversationName);
            } else {
                // For private chats, use the other participant's name
                $otherUser = $userRepository->find($participantIds[0]);
                $conversation->setConversationName($otherUser->getFirstName() . ' ' . $otherUser->getLastName());
            }

            $em->persist($conversation);

            // Add active user as participant
            $activeParticipant = new ConversationParticipant();
            $activeParticipant->setConversation($conversation);
            $activeParticipant->setUser($activeUser);
            $activeParticipant->setJoinedAt(new \DateTimeImmutable());
            $em->persist($activeParticipant);

            // Add selected participants
            foreach ($participantIds as $participantId) {
                $user = $userRepository->find($participantId);
                if ($user && $user->getId() !== $activeUser->getId()) {
                    $participant = new ConversationParticipant();
                    $participant->setConversation($conversation);
                    $participant->setUser($user);
                    $participant->setJoinedAt(new \DateTimeImmutable());
                    $em->persist($participant);
                }
            }

            $em->flush();

            return $this->redirectToRoute('messaging_show', ['id' => $conversation->getId()]);
        }

        // Get users from same family
        $familyUsers = $userRepository->findBy(['family' => $activeUser->getFamily()]);
        // Remove active user from list
        $familyUsers = array_filter($familyUsers, fn($u) => $u->getId() !== $activeUser->getId());

        // Get all conversations for sidebar
        $participants = $participantRepository->findBy(['user' => $activeUser]);
        $conversations = array_map(fn($p) => $p->getConversation(), $participants);

        return $this->render('messaging/new.html.twig', [
            'activeUser' => $activeUser,
            'conversations' => $conversations,
            'familyUsers' => $familyUsers,
            'onlineStatus' => $session->get('online_status', OnlineStatus::ONLINE->value),
        ]);
    }

    #[Route('/conversation/{id}', name: 'messaging_show')]
    public function show(
        int $id,
        SessionInterface $session,
        UserRepository $userRepository,
        ConversationRepository $conversationRepository,
        ConversationParticipantRepository $participantRepository,
        MessageRepository $messageRepository
    ): Response {
        $activeUser = $this->getActiveUser($session, $userRepository);
        
        if (!$activeUser) {
            return $this->redirectToRoute('messaging_select_user');
        }

        $conversation = $conversationRepository->find($id);
        
        if (!$conversation) {
            throw $this->createNotFoundException('Conversation not found');
        }

        // Verify user is participant
        $participant = $participantRepository->findOneBy([
            'conversation' => $conversation,
            'user' => $activeUser
        ]);

        if (!$participant) {
            throw $this->createAccessDeniedException('You are not a participant in this conversation');
        }

        // Get all conversations for sidebar
        $participants = $participantRepository->findBy(['user' => $activeUser]);
        $conversations = array_map(fn($p) => $p->getConversation(), $participants);

        // Get messages for this conversation
        $messages = $messageRepository->findBy(
            ['conversation' => $conversation],
            ['sentAt' => 'ASC']
        );

        return $this->render('messaging/show.html.twig', [
            'activeUser' => $activeUser,
            'conversations' => $conversations,
            'currentConversation' => $conversation,
            'messages' => $messages,
            'onlineStatus' => $session->get('online_status', OnlineStatus::ONLINE->value),
        ]);
    }

    #[Route('/conversation/{id}/send', name: 'messaging_send', methods: ['POST'])]
    public function sendMessage(
        int $id,
        Request $request,
        SessionInterface $session,
        UserRepository $userRepository,
        ConversationRepository $conversationRepository,
        EntityManagerInterface $em
    ): Response {
        $activeUser = $this->getActiveUser($session, $userRepository);
        
        if (!$activeUser) {
            return $this->redirectToRoute('messaging_select_user');
        }

        $conversation = $conversationRepository->find($id);
        
        if (!$conversation) {
            throw $this->createNotFoundException('Conversation not found');
        }

        $content = $request->request->get('content');
        
        if ($content) {
            $message = new Message();
            $message->setContent($content);
            $message->setConversation($conversation);
            $message->setSender($activeUser);
            $message->setSentAt(new \DateTimeImmutable());
            $message->setIsRead(false);

            $em->persist($message);
            $em->flush();
        }

        return $this->redirectToRoute('messaging_show', ['id' => $id]);
    }

    #[Route('/status/update', name: 'messaging_update_status', methods: ['POST'])]
    public function updateStatus(
        Request $request,
        SessionInterface $session
    ): Response {
        $status = $request->request->get('status');
        
        if (in_array($status, [
            OnlineStatus::ONLINE->value,
            OnlineStatus::AWAY->value,
            OnlineStatus::DO_NOT_DISTURB->value,
            OnlineStatus::OFFLINE->value
        ])) {
            $session->set('online_status', $status);
        }

        return $this->json(['success' => true]);
    }
}
