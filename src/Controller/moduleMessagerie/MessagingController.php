<?php
declare(strict_types=1);
namespace App\Controller\ModuleMessagerie;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\TypeConversation;
use App\Form\ModuleMessagerie\ConversationType;
use App\Form\MessageType;
use App\Message\AI\GenerateSmartRepliesMessage;
use App\Message\AI\SummarizeConversationMessage;
use App\Repository\AiSmartReplyRepository;
use App\Repository\ConversationParticipantRepository;
use App\Repository\ConversationRepository;
use App\Repository\MessageReactionRepository;
use App\Repository\MessageRepository;
use App\Service\AuditTrailService;
use App\ServiceModuleMessagerie\Messaging\ChatAttachmentStorage;
use App\ServiceModuleMessagerie\Messaging\MercurePublisher;
use App\ServiceModuleMessagerie\Messaging\MercureTokenFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/messaging', name: 'portal_messaging_')]
#[IsGranted('ROLE_USER')]
class MessagingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConversationRepository $conversationRepository,
        private readonly MessageRepository $messageRepository,
        private readonly ConversationParticipantRepository $participantRepository,
        private readonly MessageReactionRepository $reactionRepository,
        private readonly AiSmartReplyRepository $aiSmartReplyRepository,
        private readonly MercurePublisher $mercurePublisher,
        private readonly MercureTokenFactory $mercureTokenFactory,
        private readonly AuditTrailService $auditTrailService,
        private readonly ChatAttachmentStorage $attachmentStorage,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $conversations = $this->conversationRepository->findUserConversations($user);

        return $this->render('ui_portal/messaging/index.html.twig', [
            'active_menu'          => 'messaging',
            'conversations'        => $conversations,
            'current_conversation' => null,
            'mercure_token'        => $this->mercureTokenFactory->buildSubscriberToken($user),
            'mercure_public_url'   => $this->getParameter('mercure.public_url'),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ConversationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $participants     = $form->get('participants')->getData();
            $conversationName = $form->get('conversationName')->getData();
            $allParticipants = [...$participants, $user];

            if (count($allParticipants) < 2) {
                $this->addFlash('error', 'You must select at least one other participant.');
                return $this->redirectToRoute('portal_messaging_create');
            }

            if (count($allParticipants) === 2) {
                $otherUser = $participants[0];
                $existing  = $this->conversationRepository->findPrivateConversationBetween($user, $otherUser);
                if ($existing) {
                    return $this->redirectToRoute('portal_messaging_show', ['id' => $existing->getId()]);
                }
                $type = TypeConversation::PRIVATE;
            } else {
                $type = TypeConversation::GROUP;
                if (empty($conversationName)) {
                    $form->get('conversationName')->addError(new FormError('Group name is required.'));
                    return $this->render('ui_portal/messaging/create.html.twig', [
                        'active_menu' => 'messaging',
                        'form'        => $form->createView(),
                    ]);
                }
            }

            $conversation = new Conversation();
            $conversation->setFamily($user->getFamily());
            $conversation->setCreatedBy($user);
            $conversation->setCreatedAt(new DateTimeImmutable());
            $conversation->setType($type);
            $conversation->setConversationName($conversationName ?: 'Private Chat');
            $this->entityManager->persist($conversation);

            foreach ($allParticipants as $participantUser) {
                $participant = new ConversationParticipant();
                $participant->setConversation($conversation);
                $participant->setUser($participantUser);
                $participant->setJoinedAt(new DateTimeImmutable());
                $this->entityManager->persist($participant);
            }
            $this->entityManager->flush();

            return $this->redirectToRoute('portal_messaging_show', ['id' => $conversation->getId()]);
        }
        return $this->render('ui_portal/messaging/create.html.twig', [
            'active_menu' => 'messaging',
            'form'        => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(Conversation $conversation, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->participantRepository->isUserParticipant($conversation, $user)) {
            throw $this->createAccessDeniedException('Forbidden');
        }

        $message = new Message();
        $form    = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message->setConversation($conversation);
            $message->setSender($user);
            $message->setSentAt(new DateTimeImmutable());
            $message->setIsRead(false);

            $parentId = (int) $form->get('parentMessageId')->getData();
            if ($parentId > 0) {
                $parentMessage = $this->messageRepository->find($parentId);
                if ($parentMessage && $parentMessage->getConversation()?->getId() === $conversation->getId()) {
                    $message->setParentMessage($parentMessage);
                }
            }

            $attachmentFile = $form->get('attachment')->getData();
            if ($attachmentFile) {
                try {
                    $attachmentUrl = $this->attachmentStorage->store($attachmentFile);
                    $message->setAttachmentURL($attachmentUrl);
                } catch (\Exception $e) {}
            }

            $this->entityManager->persist($message);
            $this->entityManager->flush();

            try { $this->mercurePublisher->publishNewMessage($message); } catch (\Throwable $e) {}

            return $this->redirectToRoute('portal_messaging_show', ['id' => $conversation->getId()]);
        }

        $conversations = $this->conversationRepository->findUserConversations($user);
        $messages      = $this->messageRepository->findMessagesByConversation($conversation);

        $unreadMessages = array_filter($messages, fn (Message $m) => $m->getSender() !== $user && !$m->isRead());
        if (count($unreadMessages) > 0) {
            foreach ($unreadMessages as $um) {
                $um->setIsRead(true);
                try { $this->mercurePublisher->publishReadReceipt($um, $user); } catch (\Throwable $e) {}
            }
            $this->entityManager->flush();
        }

        $messageIds = array_filter(array_map(fn (Message $m) => $m->getId(), $messages));
        $reactions  = $this->reactionRepository->groupedByEmojiForMessages(array_values($messageIds));

        return $this->render('ui_portal/messaging/index.html.twig', [
            'active_menu'          => 'messaging',
            'conversations'        => $conversations,
            'current_conversation' => $conversation,
            'messages'             => $messages,
            'reactions'            => $reactions,
            'form'                 => $form->createView(),
            'mercure_token'        => $this->mercureTokenFactory->buildSubscriberToken($user, $conversation),
            'mercure_public_url'   => $this->getParameter('mercure.public_url'),
        ]);
    }

    #[Route('/{id}/typing', name: 'typing', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function typing(Conversation $conversation, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->participantRepository->isUserParticipant($conversation, $user)) {
             return new JsonResponse(['error' => 'Forbidden'], 403);
        }
        $payload   = json_decode((string) $request->getContent(), true);
        $isTyping  = (bool) ($payload['isTyping'] ?? false);
        try { $this->mercurePublisher->publishTyping($conversation, $user, $isTyping); } catch (\Throwable $e) {}
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/{id}/read', name: 'read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markRead(Conversation $conversation, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->participantRepository->isUserParticipant($conversation, $user)) {
             return new JsonResponse(['error' => 'Forbidden'], 403);
        }
        $payload    = json_decode((string) $request->getContent(), true);
        $messageIds = array_map('intval', (array) ($payload['messageIds'] ?? []));
        if ($messageIds === []) return new JsonResponse(['ok' => true, 'marked' => 0]);
        $messages = $this->messageRepository->findBy(['conversation' => $conversation]);
        $marked   = 0;
        foreach ($messages as $msg) {
            if (!in_array($msg->getId(), $messageIds, true)) continue;
            if ($msg->getSender() === $user || $msg->isRead()) continue;
            $msg->setIsRead(true);
            $marked++;
            try { $this->mercurePublisher->publishReadReceipt($msg, $user); } catch (\Throwable $e) {}
        }
        if ($marked > 0) $this->entityManager->flush();
        return new JsonResponse(['ok' => true, 'marked' => $marked]);
    }

    #[Route('/presence', name: 'presence', methods: ['POST'])]
    public function presence(Request $request): JsonResponse
    {
        /** @var User $user */
        $user    = $this->getUser();
        $payload = json_decode((string) $request->getContent(), true);
        $online  = (bool) ($payload['online'] ?? true);
        try { $this->mercurePublisher->publishPresence($user, $online); } catch (\Throwable $e) {}
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/mercure-token', name: 'mercure_token', methods: ['GET'])]
    public function mercureToken(Request $request): JsonResponse
    {
        /** @var User $user */
        $user           = $this->getUser();
        $conversationId = $request->query->getInt('conversation');
        $conversation = null;
        if ($conversationId > 0) {
            $conversation = $this->entityManager->getRepository(Conversation::class)->find($conversationId);
            if ($conversation && !$this->participantRepository->isUserParticipant($conversation, $user)) {
                $conversation = null;
            }
        }
        return new JsonResponse([
            'token'     => $this->mercureTokenFactory->buildSubscriberToken($user, $conversation),
            'publicUrl' => $this->getParameter('mercure.public_url'),
        ]);
    }

    #[Route('/{id}/ai/suggest-replies', name: 'ai_suggest_replies', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function suggestReplies(Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$this->participantRepository->isUserParticipant($conversation, $user)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        // Check if there are unread messages
        $messages = $this->messageRepository->findMessagesByConversation($conversation);
        $unreadMessages = array_filter($messages, fn (Message $m) => $m->getSender() !== $user && !$m->isRead());

        // Trigger AI generation if there are unread messages
        if (count($unreadMessages) > 0) {
            $userId = $user->getId();
            if ($userId === null) {
                return new JsonResponse(['error' => 'User identifier missing'], Response::HTTP_BAD_REQUEST);
            }

            $message = new GenerateSmartRepliesMessage($conversation->getId(), $userId);
            $this->messageBus->dispatch($message);
        }

        return new JsonResponse(['status' => 'accepted'], Response::HTTP_ACCEPTED);
    }

    #[Route('/{id}/ai/summarize', name: 'ai_summarize', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function summarize(Conversation $conversation, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$this->participantRepository->isUserParticipant($conversation, $user)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        // Check if conversation has enough messages (more than 10)
        $messages = $this->messageRepository->findMessagesByConversation($conversation);
        if (count($messages) <= 10) {
            return new JsonResponse(['error' => 'Not enough messages to summarize'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $limit = (int) ($payload['limit'] ?? 50);
        $limit = max(10, min($limit, 100)); // Clamp between 10 and 100

        // Dispatch summarization message
        $userId = $user->getId();
        if ($userId === null) {
            return new JsonResponse(['error' => 'User identifier missing'], Response::HTTP_BAD_REQUEST);
        }

        $message = new SummarizeConversationMessage($conversation->getId(), $userId, $limit);
        $this->messageBus->dispatch($message);

        return new JsonResponse(['status' => 'accepted'], Response::HTTP_ACCEPTED);
    }

    #[Route('/{id}/ai/use-reply', name: 'ai_use_reply', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function useSmartReply(Conversation $conversation, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$this->participantRepository->isUserParticipant($conversation, $user)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $suggestionText = $payload['suggestion'] ?? '';

        if (empty($suggestionText)) {
            return new JsonResponse(['error' => 'Suggestion text is required'], Response::HTTP_BAD_REQUEST);
        }

        // Find and mark the smart reply as used
        $smartReply = $this->aiSmartReplyRepository->findUnusedForUserAndConversation($user, $conversation);
        if ($smartReply) {
            $smartReply->setIsUsed(true);
            $this->entityManager->flush();

            // Record audit trail
            $this->auditTrailService->record(
                $user,
                'ai.smart_reply.used',
                [
                    'conversationId' => $conversation->getId(),
                    'suggestionText' => $suggestionText,
                ],
                $conversation->getFamily()
            );
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/presence/ping', name: 'presence_ping', methods: ['POST'])]
    public function pingPresence(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->mercurePublisher->publishPresence($user, true);
        } catch (\Throwable $e) {
        }
        
        return new JsonResponse(['ok' => true]);
    }
}
