<?php

namespace App\Controller\ModuleDocuments\FrontOffice\API;

use App\Entity\Document;
use App\Entity\Family;
use App\Entity\User;
use App\Service\ActiveFamilyResolver;
use App\Service\DocumentKeyDataExtractor;
use App\Service\DocumentTextExtractor;
use App\Service\HuggingFaceZeroShotClassifierClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/documents')]
final class DocumentKeyDataController extends AbstractController
{
    /**
     * @var list<string>
     */
    private const DETECTION_LABELS = ['Facture', 'Contrat', 'Autre'];

    #[Route('/{id}/extract-key-data', name: 'app_document_extract_key_data', methods: ['POST', 'GET'], requirements: ['id' => '\d+'])]
    public function extract(
        Document $document,
        ActiveFamilyResolver $familyResolver,
        DocumentTextExtractor $documentTextExtractor,
        DocumentKeyDataExtractor $documentKeyDataExtractor,
        HuggingFaceZeroShotClassifierClient $classifierClient
    ): JsonResponse {
        try {
            $family = $this->resolveFamily($familyResolver);
            $this->assertSameFamily($family, $document->getFamily());

            $extracted = $documentTextExtractor->extract($document);
            $detection = $this->detectDocumentType(
                $extracted['text'],
                $documentKeyDataExtractor,
                $classifierClient
            );

            $keyData = $documentKeyDataExtractor->extract($extracted['text'], $detection['document_type']);

            return $this->json([
                'ok' => true,
                'document' => [
                    'id' => $document->getId(),
                    'name' => $document->getFileName(),
                    'type' => $document->getFileType(),
                ],
                'detection' => [
                    'document_type' => $detection['document_type'],
                    'method' => $detection['method'],
                    'confidence' => $detection['confidence'],
                    'raw_label' => $detection['raw_label'],
                    'model' => $detection['model'],
                ],
                'extraction' => $keyData,
                'meta' => [
                    'text_source' => $extracted['source'],
                    'input_format' => $extracted['input_format'],
                    'was_converted' => $extracted['was_converted'],
                    'input_chars' => mb_strlen((string) $extracted['text']),
                ],
            ]);
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
                'error' => 'Unexpected extraction error.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{
     *   document_type: string,
     *   method: string,
     *   confidence: float|null,
     *   raw_label: string|null,
     *   model: string|null
     * }
     */
    private function detectDocumentType(
        string $text,
        DocumentKeyDataExtractor $documentKeyDataExtractor,
        HuggingFaceZeroShotClassifierClient $classifierClient
    ): array {
        if ($classifierClient->isEnabled()) {
            try {
                $classification = $classifierClient->classify($text, self::DETECTION_LABELS, false);
                $rawLabel = (string) ($classification['top_label'] ?? '');

                return [
                    'document_type' => $this->mapTypeLabel($rawLabel),
                    'method' => 'huggingface_zero_shot',
                    'confidence' => isset($classification['top_score']) ? (float) $classification['top_score'] : null,
                    'raw_label' => $rawLabel !== '' ? $rawLabel : null,
                    'model' => isset($classification['model']) ? (string) $classification['model'] : null,
                ];
            } catch (\Throwable) {
                // Graceful fallback to local heuristics when external detection is unavailable.
            }
        }

        return [
            'document_type' => $documentKeyDataExtractor->guessTypeFromText($text),
            'method' => 'local_heuristic',
            'confidence' => null,
            'raw_label' => null,
            'model' => null,
        ];
    }

    private function mapTypeLabel(string $label): string
    {
        $normalized = trim(mb_strtolower($label));
        if ($normalized === 'facture') {
            return 'facture';
        }
        if ($normalized === 'contrat') {
            return 'contrat';
        }

        return 'autre';
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
