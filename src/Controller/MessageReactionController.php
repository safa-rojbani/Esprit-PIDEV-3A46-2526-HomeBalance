<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationParticipantRepository;
use App\Service\Messaging\ReactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal/messaging/message', name: 'portal_messaging_message_')]
#[IsGranted('ROLE_USER')]
final class MessageReactionController extends AbstractController
{
    public function __construct(
        private readonly ReactionService $reactionService,
        private readonly ConversationParticipantRepository $participantRepository,
    ) {
    }

    /**
     * Toggle a reaction on a message.
     *
     * POST /portal/messaging/message/{id}/react
     * Body: { "emoji": "👍" }
     */
    #[Route('/{id}/react', name: 'react', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function react(Message $message, Request $request): JsonResponse
    {
        /** @var User $user */
        $user         = $this->getUser();
        $conversation = $message->getConversation();

        if ($conversation === null || !$this->participantRepository->isUserParticipant($conversation, $user)) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $emoji   = isset($payload['emoji']) ? (string) $payload['emoji'] : '';

        if ($emoji === '') {
            return new JsonResponse(['error' => 'Missing emoji.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->reactionService->toggle($message, $user, $emoji);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'ok'        => true,
            'action'    => $result['action'],
            'messageId' => $message->getId(),
            'reactions' => $result['reactions'],
        ]);
    }
}
