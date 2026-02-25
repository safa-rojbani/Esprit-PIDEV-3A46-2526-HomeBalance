<?php

namespace App\Controller\ModuleDocuments\FrontOffice\API;

use App\Entity\Document;
use App\Entity\Family;
use App\Entity\User;
use App\Service\ActiveFamilyResolver;
use App\Service\CloudConvertClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/documents')]
final class DocumentConvertController extends AbstractController
{
    #[Route('/{id}/convert/formats', name: 'app_document_convert_formats', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function formats(
        Document $document,
        ActiveFamilyResolver $familyResolver,
        CloudConvertClient $cloudConvertClient
    ): JsonResponse {
        try {
            $family = $this->resolveFamily($familyResolver);
            $this->assertSameFamily($family, $document->getFamily());

            $inputFormat = $cloudConvertClient->detectInputFormat($document->getFilePath(), $document->getFileType());
            if ($inputFormat === null) {
                return $this->json([
                    'ok' => false,
                    'error' => 'Unable to detect input format for this document.',
                ], 400);
            }

            $outputFormats = $cloudConvertClient->listOutputFormats($inputFormat);

            return $this->json([
                'ok' => true,
                'input_format' => $inputFormat,
                'output_formats' => $outputFormats,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Access denied.',
            ], 403);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/convert', name: 'app_document_convert', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function convert(
        Document $document,
        Request $request,
        ActiveFamilyResolver $familyResolver,
        CloudConvertClient $cloudConvertClient
    ): JsonResponse {
        try {
            // Large video conversions can exceed the default PHP request timeout.
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }
            @ini_set('max_execution_time', '600');

            $family = $this->resolveFamily($familyResolver);
            $this->assertSameFamily($family, $document->getFamily());

            $inputFormat = $cloudConvertClient->detectInputFormat($document->getFilePath(), $document->getFileType());
            if ($inputFormat === null) {
                return $this->json([
                    'ok' => false,
                    'error' => 'Unable to detect input format for this document.',
                ], 400);
            }

            $outputFormat = $this->readOutputFormat($request);
            if ($outputFormat === '') {
                return $this->json([
                    'ok' => false,
                    'error' => 'output_format is required.',
                ], 400);
            }

            $relativePath = $document->getFilePath();
            if ($relativePath === null || $relativePath === '') {
                return $this->json([
                    'ok' => false,
                    'error' => 'Document path is missing.',
                ], 400);
            }

            $projectDir = (string) $this->getParameter('kernel.project_dir');
            $absolutePath = rtrim($projectDir, '/\\') . '/public/' . ltrim($relativePath, '/\\');

            $targetFileName = $this->buildTargetFileName((string) $document->getFileName(), $outputFormat);
            $result = $cloudConvertClient->convertLocalFile(
                $absolutePath,
                $inputFormat,
                $outputFormat,
                $targetFileName
            );

            return $this->json([
                'ok' => true,
                'result' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Access denied.',
            ], 403);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function readOutputFormat(Request $request): string
    {
        $value = $request->request->get('output_format');
        if (is_string($value) && trim($value) !== '') {
            return strtolower(trim($value));
        }

        $content = (string) $request->getContent();
        if ($content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['output_format']) && is_string($decoded['output_format'])) {
                return strtolower(trim($decoded['output_format']));
            }
        }

        return '';
    }

    private function buildTargetFileName(string $originalName, string $outputFormat): string
    {
        $name = trim($originalName);
        if ($name === '') {
            $name = 'document';
        }

        $name = preg_replace('/\.[a-z0-9]+$/i', '', $name) ?? $name;
        $name = trim($name);
        if ($name === '') {
            $name = 'document';
        }

        return $name . '.' . strtolower($outputFormat);
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
