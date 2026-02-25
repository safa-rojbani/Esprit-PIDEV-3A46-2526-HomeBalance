<?php

namespace App\Service;

use App\Entity\Document;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DocumentTextExtractor
{
    public function __construct(
        private readonly CloudConvertClient $cloudConvertClient,
        private readonly HuggingFaceImageOcrClient $huggingFaceImageOcrClient,
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir
    ) {
    }

    /**
     * @return array{
     *   text: string,
     *   source: string,
     *   was_converted: bool,
     *   input_format: string|null
     * }
     */
    public function extract(Document $document): array
    {
        $absolutePath = $this->resolveAbsolutePath($document);
        $inputFormat = $this->cloudConvertClient->detectInputFormat($document->getFilePath(), $document->getFileType());

        if ($this->canReadAsText($document, $absolutePath, $inputFormat)) {
            $text = $this->readLocalTextFile($absolutePath);

            return [
                'text' => $text,
                'source' => 'local_text',
                'was_converted' => false,
                'input_format' => $inputFormat,
            ];
        }

        if ($inputFormat === null) {
            throw new \RuntimeException('Unsupported document format for text extraction.');
        }

        if ($this->isImageFormat($inputFormat) && $this->huggingFaceImageOcrClient->isEnabled()) {
            $text = $this->huggingFaceImageOcrClient->extractTextFromImage($absolutePath);

            return [
                'text' => $this->normalizeText($text),
                'source' => 'huggingface_ocr',
                'was_converted' => false,
                'input_format' => $inputFormat,
            ];
        }

        $targetName = $this->buildConvertedFileName($document->getFileName());
        try {
            $conversion = $this->cloudConvertClient->convertLocalFile($absolutePath, $inputFormat, 'txt', $targetName);
        } catch (\RuntimeException $exception) {
            $message = mb_strtolower($exception->getMessage());
            if (str_contains($message, 'run out of conversion credits')) {
                throw new \RuntimeException(
                    'CloudConvert credits exhausted for text conversion. For image files, OCR fallback is used automatically; for this format, recharge CloudConvert credits or upload an image version.'
                );
            }

            throw $exception;
        }
        $downloadUrl = (string) ($conversion['download_url'] ?? '');
        if ($downloadUrl === '') {
            throw new \RuntimeException('CloudConvert did not return a download URL for text extraction.');
        }

        $text = $this->downloadText($downloadUrl);

        return [
            'text' => $text,
            'source' => 'cloudconvert_txt',
            'was_converted' => true,
            'input_format' => $inputFormat,
        ];
    }

    private function resolveAbsolutePath(Document $document): string
    {
        $relativePath = $document->getFilePath();
        if ($relativePath === null || trim($relativePath) === '') {
            throw new \RuntimeException('Document path is missing.');
        }

        $absolutePath = rtrim($this->projectDir, '/\\') . '/public/' . ltrim($relativePath, '/\\');
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new \RuntimeException('Document file is not readable on server.');
        }

        return $absolutePath;
    }

    private function canReadAsText(Document $document, string $absolutePath, ?string $inputFormat): bool
    {
        $mimeType = strtolower((string) $document->getFileType());
        if (str_starts_with($mimeType, 'text/')) {
            return true;
        }

        $extension = strtolower((string) pathinfo($absolutePath, \PATHINFO_EXTENSION));
        if (\in_array($extension, ['txt', 'md', 'csv', 'json', 'xml', 'log'], true)) {
            return true;
        }

        return \in_array((string) $inputFormat, ['txt', 'md', 'csv', 'json', 'xml'], true);
    }

    private function isImageFormat(string $inputFormat): bool
    {
        return \in_array(strtolower($inputFormat), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'heif'], true);
    }

    private function readLocalTextFile(string $absolutePath): string
    {
        $content = file_get_contents($absolutePath);
        if (!is_string($content)) {
            throw new \RuntimeException('Unable to read document text content.');
        }

        return $this->normalizeText($content);
    }

    private function downloadText(string $downloadUrl): string
    {
        $response = $this->httpClient->request('GET', $downloadUrl, [
            'timeout' => 45,
            'max_redirects' => 5,
        ]);

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        if ($statusCode >= 400) {
            throw new \RuntimeException('Unable to download converted text file (HTTP ' . $statusCode . ').');
        }

        return $this->normalizeText($content);
    }

    private function normalizeText(string $raw): string
    {
        $text = str_replace("\xEF\xBB\xBF", '', $raw);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        if ($text === '') {
            throw new \RuntimeException('No textual content found in the document.');
        }

        return $text;
    }

    private function buildConvertedFileName(?string $originalName): string
    {
        $base = trim((string) $originalName);
        if ($base === '') {
            $base = 'document';
        }

        $base = preg_replace('/\.[a-z0-9]+$/i', '', $base) ?? $base;
        $base = trim($base);
        if ($base === '') {
            $base = 'document';
        }

        return $base . '.txt';
    }
}
