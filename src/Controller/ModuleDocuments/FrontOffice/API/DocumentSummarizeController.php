<?php

namespace App\Controller\ModuleDocuments\FrontOffice\API;

use App\Entity\Document;
use App\Entity\Family;
use App\Entity\User;
use App\Service\ActiveFamilyResolver;
use App\Service\DocumentTextExtractor;
use App\Service\HuggingFaceSummarizerClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/documents')]
final class DocumentSummarizeController extends AbstractController
{
    #[Route('/{id}/summarize', name: 'app_document_summarize', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function summarize(
        Document $document,
        Request $request,
        ActiveFamilyResolver $familyResolver,
        DocumentTextExtractor $documentTextExtractor,
        HuggingFaceSummarizerClient $summarizerClient
    ): JsonResponse {
        try {
            $family = $this->resolveFamily($familyResolver);
            $this->assertSameFamily($family, $document->getFamily());

            if (!$summarizerClient->isEnabled()) {
                return $this->json([
                    'ok' => false,
                    'error' => 'HUGGINGFACE_API_KEY is not configured.',
                ], 503);
            }

            $maxLength = $this->readIntOption($request, 'max_length', 140);
            $minLength = $this->readIntOption($request, 'min_length', 40);

            $extracted = $documentTextExtractor->extract($document);
            $summary = $summarizerClient->summarize($extracted['text'], $maxLength, $minLength);

            return $this->json([
                'ok' => true,
                'document' => [
                    'id' => $document->getId(),
                    'name' => $document->getFileName(),
                    'type' => $document->getFileType(),
                ],
                'summary' => $summary['summary'],
                'meta' => [
                    'model' => $summary['model'],
                    'input_length' => $summary['input_length'],
                    'input_truncated' => $summary['truncated'],
                    'text_source' => $extracted['source'],
                    'input_format' => $extracted['input_format'],
                    'was_converted' => $extracted['was_converted'],
                    'max_length' => $maxLength,
                    'min_length' => $minLength,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException) {
            return $this->json([
                'ok' => false,
                'error' => 'Access denied.',
            ], 403);
        } catch (\RuntimeException $e) {
            return $this->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Unexpected summarization error.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    private function readIntOption(Request $request, string $key, int $default): int
    {
        $requestValue = $request->request->get($key);
        if (is_scalar($requestValue) && (string) $requestValue !== '') {
            return (int) $requestValue;
        }

        $raw = (string) $request->getContent();
        if ($raw !== '') {
            $payload = json_decode($raw, true);
            if (is_array($payload) && isset($payload[$key]) && is_numeric((string) $payload[$key])) {
                return (int) $payload[$key];
            }
        }

        return $default;
    }

    private function resolveFamily(ActiveFamilyResolver $familyResolver): Family
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $family = $familyResolver->resolveForUser($user);
        if ($family === null) {
            throw $this->createAccessDeniedException();
        }

        return $family;
    }

    private function assertSameFamily(Family $family, ?Family $targetFamily): void
    {
        if ($targetFamily === null || $targetFamily->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}

