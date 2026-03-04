<?php

namespace App\Controller\ModuleEvenement\Client\Api;

use App\Service\ModuleEvenement\OllamaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/portal/api/ai')]
class AiController extends AbstractController
{
    #[Route('/generate-description', name: 'portal_ai_generate_description', methods: ['POST'])]
    public function generateDescription(Request $request, OllamaService $ollamaService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        $titre = isset($payload['titre']) ? trim((string) $payload['titre']) : '';
        $lieu = isset($payload['lieu']) ? trim((string) $payload['lieu']) : '';
        $typeNom = isset($payload['typeNom']) ? trim((string) $payload['typeNom']) : '';

        if ($titre === '' || mb_strlen($titre) < 3) {
            return $this->json(['success' => false, 'error' => 'Le titre est requis'], 400);
        }

        try {
            $description = $ollamaService->generateDescription($titre, $lieu, $typeNom);
            return $this->json(['success' => true, 'description' => $description]);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'error' => 'Service IA indisponible'], 503);
        }
    }

    #[Route('/status', name: 'portal_ai_status', methods: ['GET'])]
    public function status(OllamaService $ollamaService): JsonResponse
    {
        return $this->json(['available' => $ollamaService->isAvailable()]);
    }
}
