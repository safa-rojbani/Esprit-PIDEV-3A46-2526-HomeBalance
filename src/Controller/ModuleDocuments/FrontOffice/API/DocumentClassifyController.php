<?php

namespace App\Controller\ModuleDocuments\FrontOffice\API;

use App\Entity\Document;
use App\Entity\Family;
use App\Entity\User;
use App\Service\ActiveFamilyResolver;
use App\Service\DocumentTextExtractor;
use App\Service\HuggingFaceZeroShotClassifierClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/documents')]
final class DocumentClassifyController extends AbstractController
{
    /**
     * @var list<string>
     */
    private const DEFAULT_LABELS = [
        'Administratif',
        'Facture',
        'Banque',
        'Scolaire',
        'Sante',
        'Contrat',
        'Assurance',
        'Autre',
    ];

    #[Route('/{id}/classify', name: 'app_document_classify', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function classify(
        Document $document,
        Request $request,
        ActiveFamilyResolver $familyResolver,
        DocumentTextExtractor $documentTextExtractor,
        HuggingFaceZeroShotClassifierClient $classifierClient
    ): JsonResponse {
        try {
            $family = $this->resolveFamily($familyResolver);
            $this->assertSameFamily($family, $document->getFamily());

            if (!$classifierClient->isEnabled()) {
                return $this->json([
                    'ok' => false,
                    'error' => 'HUGGINGFACE_API_KEY is not configured.',
                ], 503);
            }

            $labels = $this->readLabels($request);
            $multiLabel = $this->readBooleanOption($request, 'multi_label', false);

            $extracted = $documentTextExtractor->extract($document);
            $classification = $classifierClient->classify($extracted['text'], $labels, $multiLabel);

            return $this->json([
                'ok' => true,
                'document' => [
                    'id' => $document->getId(),
                    'name' => $document->getFileName(),
                    'type' => $document->getFileType(),
                ],
                'classification' => [
                    'top_label' => $classification['top_label'],
                    'top_score' => $classification['top_score'],
                    'labels' => $classification['labels'],
                ],
                'meta' => [
                    'model' => $classification['model'],
                    'text_source' => $extracted['source'],
                    'input_format' => $extracted['input_format'],
                    'was_converted' => $extracted['was_converted'],
                    'input_length' => $classification['input_length'],
                    'input_truncated' => $classification['input_truncated'],
                    'multi_label' => $multiLabel,
                    'requested_labels' => $labels,
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
                'error' => 'Unexpected classification error.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return list<string>
     */
    private function readLabels(Request $request): array
    {
        $labels = $request->request->all('labels');
        if (is_array($labels) && $labels !== []) {
            return array_values(array_filter(array_map(static fn ($item): string => is_string($item) ? trim($item) : '', $labels)));
        }

        $labelsRaw = trim((string) $request->request->get('labels_raw', ''));
        if ($labelsRaw !== '') {
            return $this->parseLabelsFromString($labelsRaw);
        }

        $raw = (string) $request->getContent();
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                if (isset($json['labels']) && is_array($json['labels']) && $json['labels'] !== []) {
                    return array_values(array_filter(array_map(static fn ($item): string => is_string($item) ? trim($item) : '', $json['labels'])));
                }
                if (isset($json['labels_raw']) && is_string($json['labels_raw']) && trim($json['labels_raw']) !== '') {
                    return $this->parseLabelsFromString($json['labels_raw']);
                }
            }
        }

        return self::DEFAULT_LABELS;
    }

    private function readBooleanOption(Request $request, string $key, bool $default): bool
    {
        $value = $request->request->get($key);
        if ($value !== null) {
            return filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? $default;
        }

        $raw = (string) $request->getContent();
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json) && array_key_exists($key, $json)) {
                return filter_var($json[$key], \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? $default;
            }
        }

        return $default;
    }

    /**
     * @return list<string>
     */
    private function parseLabelsFromString(string $labelsRaw): array
    {
        $chunks = preg_split('/[,;\n]+/', $labelsRaw) ?: [];

        return array_values(array_filter(array_map(static fn (string $item): string => trim($item), $chunks)));
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

