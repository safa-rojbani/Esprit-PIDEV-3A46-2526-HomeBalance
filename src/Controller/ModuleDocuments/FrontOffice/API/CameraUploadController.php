<?php

namespace App\Controller\ModuleDocuments\FrontOffice\API;

use App\Entity\Document;
use App\Entity\Gallery;
use App\Enum\EtatDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;

final class CameraUploadController extends AbstractController
{
    #[Route('/portal/documents/api/galleries/{id}/camera-upload', name: 'api_camera_upload', methods: ['POST'])]
    public function __invoke(Gallery $gallery, Request $request, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user instanceof User) {
                return $this->json(['ok' => false, 'error' => 'Unauthenticated'], 401);
            }

            $family = $familyResolver->resolveForUser($user);
            if ($family === null) {
                return $this->json(['ok' => false, 'error' => 'No active family'], 403);
            }

            // ✅ isolation Family
            if (
                !$gallery->getFamily() ||
                $gallery->getFamily()->getId() !== $family->getId()
            ) {
                return $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }

            /** @var UploadedFile|null $file */
            $file = $request->files->get('file');
            if (!$file) {
                return $this->json(['ok' => false, 'error' => 'No file provided'], 400);
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                return $this->json(['ok' => false, 'error' => 'Upload error: '.$file->getError()], 400);
            }

            $mime = (string) ($file->getMimeType() ?? '');
            $isImage = str_starts_with($mime, 'image/');
            $isVideo = str_starts_with($mime, 'video/');

            // ✅ accept image OR video
            if (!$isImage && !$isVideo) {
                return $this->json([
                    'ok' => false,
                    'error' => 'Only images or videos allowed',
                    'mime' => $mime,
                ], 400);
            }

            $projectDir = $this->getParameter('kernel.project_dir');
            $uploadDir = $projectDir . '/public/uploads/documents';

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                return $this->json(['ok' => false, 'error' => 'Cannot create upload dir'], 500);
            }
            if (!is_writable($uploadDir)) {
                return $this->json(['ok' => false, 'error' => 'Upload dir not writable: '.$uploadDir], 500);
            }

            $ext = $this->guessExtensionFromMime($mime) ?? ($isImage ? 'jpg' : 'webm');

            $prefix = $isImage ? 'camera_' : 'video_';
            $name = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            $size = $file->getSize(); // ✅ قبل move
            $file->move($uploadDir, $name);

            $now = new \DateTimeImmutable();

            $doc = new Document();
            $doc->setFileName($name);
            $doc->setFilePath('/uploads/documents/' . $name);
            $doc->setFileType($mime ?: ($isImage ? 'image/jpeg' : 'video/webm'));
            $doc->setFilesize($size ? (string) $size : null);
            $doc->setUploadedAt($now);
            $doc->setCreatedAt($now);
            $doc->setUpdatedAt($now);
            $doc->setEtat(EtatDocument::ACTIF);

            $doc->setGallery($gallery);
            $doc->setFamily($family);
            $doc->setUploadedBY($user);

            $em->persist($doc);
            $em->flush();

            return $this->json([
                'ok' => true,
                'document' => [
                    'id' => $doc->getId(),
                    'fileName' => $doc->getFileName(),
                    'filePath' => $doc->getFilePath(),
                    'fileType' => $doc->getFileType(),
                    'filesize' => $doc->getFilesize(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ], 500);
        }
    }

    private function guessExtensionFromMime(string $mime): ?string
    {
        $map = [
            // images
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/heic' => 'heic',
            'image/heif' => 'heif',

            // videos
            'video/webm' => 'webm',
            'video/mp4'  => 'mp4',
            'video/quicktime' => 'mov',
            'video/ogg'  => 'ogv',
            'video/x-matroska' => 'mkv',
        ];

        if (isset($map[$mime])) return $map[$mime];

        if (str_contains($mime, '/')) {
            $ext = explode('/', $mime, 2)[1] ?? null;
            if ($ext) {
                $ext = preg_replace('~[^a-z0-9]+~i', '', $ext);
                return $ext ?: null;
            }
        }
        return null;
    }
}
