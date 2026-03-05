<?php

namespace App\Service;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CloudConvertClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $baseUri = 'https://api.cloudconvert.com/v2',
        private readonly string $syncBaseUri = 'https://sync.api.cloudconvert.com/v2'
    ) {
    }

    /**
     * @return list<string>
     */
    public function listOutputFormats(string $inputFormat): array
    {
        $this->ensureApiKey();

        $inputFormat = $this->normalizeFormat($inputFormat);
        if ($inputFormat === '') {
            throw new \InvalidArgumentException('Input format is required.');
        }

        $response = $this->httpClient->request('GET', rtrim($this->baseUri, '/') . '/convert/formats', [
            'headers' => $this->defaultHeaders(),
            'query' => [
                'filter[input_format]' => $inputFormat,
            ],
        ]);

        $payload = $this->decodeJson($response);
        $rows = $payload['data'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $formats = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['output_format']) || !is_string($row['output_format'])) {
                continue;
            }

            $output = $this->normalizeFormat($row['output_format']);
            if ($output === '' || $output === $inputFormat) {
                continue;
            }

            $formats[$output] = true;
        }

        $result = array_keys($formats);
        sort($result);

        return $result;
    }

    /**
     * @return array{
     *   job_id:string,
     *   input_format:string,
     *   output_format:string,
     *   filename:string|null,
     *   download_url:string,
     *   files:list<array{filename?:string,url?:string}>,
     *   expires_at:string|null
     * }
     */
    public function convertLocalFile(
        string $absolutePath,
        string $inputFormat,
        string $outputFormat,
        ?string $targetFileName = null
    ): array {
        $this->ensureApiKey();

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new \RuntimeException(sprintf('File is not readable: %s', $absolutePath));
        }

        $inputFormat = $this->normalizeFormat($inputFormat);
        $outputFormat = $this->normalizeFormat($outputFormat);
        if ($inputFormat === '' || $outputFormat === '') {
            throw new \InvalidArgumentException('Input/output format is required.');
        }
        if ($inputFormat === $outputFormat) {
            throw new \InvalidArgumentException('Output format must be different from input format.');
        }

        $allowedOutputs = $this->listOutputFormats($inputFormat);
        if (!in_array($outputFormat, $allowedOutputs, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Conversion %s -> %s is not available.',
                $inputFormat,
                $outputFormat
            ));
        }

        $job = $this->createConversionJob($inputFormat, $outputFormat, $targetFileName);
        $jobId = $job['id'] ?? null;
        if (!is_string($jobId) || $jobId === '') {
            throw new \RuntimeException('CloudConvert job id is missing.');
        }

        $importTask = $this->findTaskByName($job, 'import-local-file');
        $form = $importTask['result']['form'] ?? null;
        if (!is_array($form) || !isset($form['url']) || !is_string($form['url'])) {
            throw new \RuntimeException('CloudConvert upload form is missing.');
        }

        $parameters = $form['parameters'] ?? [];
        if (!is_array($parameters)) {
            throw new \RuntimeException('CloudConvert upload parameters are invalid.');
        }

        $uploadTimedOut = false;
        try {
            $this->uploadToImportTask(
                (string) $form['url'],
                $parameters,
                $absolutePath
            );
        } catch (\RuntimeException $e) {
            if (!str_contains($e->getMessage(), 'upload timeout/network error')) {
                throw $e;
            }

            // Sometimes the upload is accepted remotely but the local HTTP client times out
            // while waiting for the final response; continue with job wait to verify status.
            $uploadTimedOut = true;
        }

        $finishedJob = $this->waitForJob($jobId, $uploadTimedOut ? 900 : 300);
        $exportTask = $this->findTaskByName($finishedJob, 'export-local-file');
        $files = $exportTask['result']['files'] ?? [];
        if (!is_array($files) || $files === []) {
            throw new \RuntimeException('No converted file URL returned by CloudConvert.');
        }

        $first = $files[0];
        if (!is_array($first) || !isset($first['url']) || !is_string($first['url'])) {
            throw new \RuntimeException('Converted file URL is missing.');
        }

        $downloadUrl = $first['url'];
        $filename = isset($first['filename']) && is_string($first['filename']) ? $first['filename'] : null;

        return [
            'job_id' => $jobId,
            'input_format' => $inputFormat,
            'output_format' => $outputFormat,
            'filename' => $filename,
            'download_url' => $downloadUrl,
            'files' => $files,
            'expires_at' => $this->extractExpiryFromTempUrl($downloadUrl),
        ];
    }

    public function detectInputFormat(?string $filePath, ?string $mimeType): ?string
    {
        if (is_string($filePath) && $filePath !== '') {
            $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
            if ($extension !== '') {
                return match ($extension) {
                    'jpeg' => 'jpg',
                    default => $extension,
                };
            }
        }

        $mime = strtolower((string) $mimeType);
        if ($mime === '') {
            return null;
        }

        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/ogg' => 'ogv',
            'video/x-matroska' => 'mkv',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            default => null,
        };
    }

    private function createConversionJob(string $inputFormat, string $outputFormat, ?string $targetFileName): array
    {
        $convertTask = [
            'operation' => 'convert',
            'input' => 'import-local-file',
            'input_format' => $inputFormat,
            'output_format' => $outputFormat,
        ];

        if ($targetFileName !== null && trim($targetFileName) !== '') {
            $convertTask['filename'] = $targetFileName;
        }

        $response = $this->httpClient->request('POST', rtrim($this->baseUri, '/') . '/jobs', [
            'headers' => $this->defaultHeaders(),
            'json' => [
                'tasks' => [
                    'import-local-file' => [
                        'operation' => 'import/upload',
                    ],
                    'convert-local-file' => $convertTask,
                    'export-local-file' => [
                        'operation' => 'export/url',
                        'input' => 'convert-local-file',
                    ],
                ],
            ],
        ]);

        $payload = $this->decodeJson($response);
        $job = $payload['data'] ?? null;
        if (!is_array($job)) {
            throw new \RuntimeException('CloudConvert job payload is missing.');
        }

        return $job;
    }

    /**
     * @param array<string, scalar|null> $parameters
     */
    private function uploadToImportTask(string $uploadUrl, array $parameters, string $absolutePath): void
    {
        $fields = [];
        foreach ($parameters as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_bool($value)) {
                $fields[$key] = $value ? '1' : '0';
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $fields[$key] = $value === null ? '' : (string) $value;
            }
        }
        $fields['file'] = DataPart::fromPath($absolutePath, basename($absolutePath));

        $formData = new FormDataPart($fields);

        foreach ([1, 2] as $attempt) {
            try {
                $response = $this->httpClient->request('POST', $uploadUrl, [
                    'headers' => $formData->getPreparedHeaders()->toArray(),
                    'body' => $formData->bodyToIterable(),
                    'max_redirects' => 5,
                    'timeout' => 600,
                    'max_duration' => 1200,
                ]);

                $statusCode = $response->getStatusCode();
                if ($statusCode >= 400) {
                    throw new \RuntimeException('CloudConvert upload step failed (HTTP ' . $statusCode . ').');
                }

                return;
            } catch (TransportExceptionInterface $e) {
                if ($attempt === 2) {
                    throw new \RuntimeException('CloudConvert upload timeout/network error: ' . $e->getMessage(), 0, $e);
                }
            }
        }
    }

    private function waitForJob(string $jobId, int $timeoutSeconds = 300): array
    {
        $response = $this->httpClient->request('GET', rtrim($this->syncBaseUri, '/') . '/jobs/' . $jobId . '/wait', [
            'headers' => $this->defaultHeaders(),
            'timeout' => max(30, $timeoutSeconds),
        ]);

        $payload = $this->decodeJson($response);
        $job = $payload['data'] ?? null;
        if (!is_array($job)) {
            throw new \RuntimeException('CloudConvert finished job payload is missing.');
        }

        $status = $job['status'] ?? null;
        if ($status === 'error') {
            throw new \RuntimeException($this->extractJobErrorMessage($job));
        }

        return $job;
    }

    /**
     * @return array<string, mixed>
     */
    private function findTaskByName(array $job, string $taskName): array
    {
        $tasks = $job['tasks'] ?? null;
        if (!is_array($tasks)) {
            throw new \RuntimeException('CloudConvert job tasks are missing.');
        }

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            if (($task['name'] ?? null) === $taskName) {
                return $task;
            }
        }

        throw new \RuntimeException(sprintf('CloudConvert task "%s" not found.', $taskName));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($statusCode >= 400) {
            $message = $data['message']
                ?? $data['error']['message']
                ?? $data['error']
                ?? null;

            $text = is_string($message) && $message !== '' ? $message : ('CloudConvert request failed with HTTP ' . $statusCode);
            throw new \RuntimeException($text);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function extractJobErrorMessage(array $job): string
    {
        $tasks = $job['tasks'] ?? null;
        if (is_array($tasks)) {
            foreach ($tasks as $task) {
                if (!is_array($task)) {
                    continue;
                }

                if (($task['status'] ?? null) !== 'error') {
                    continue;
                }

                $message = $task['message'] ?? null;
                if (is_string($message) && $message !== '') {
                    return $message;
                }
            }
        }

        return 'CloudConvert conversion failed.';
    }

    private function extractExpiryFromTempUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['query'])) {
            return null;
        }

        parse_str((string) $parts['query'], $query);
        $expires = $query['temp_url_expires'] ?? null;
        if (!is_scalar($expires) || !is_numeric((string) $expires)) {
            return null;
        }

        try {
            $dt = new \DateTimeImmutable('@' . (int) $expires);
            return $dt->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));
        $format = preg_replace('/[^a-z0-9.+-]/', '', $format);

        return $format ?? '';
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    private function ensureApiKey(): void
    {
        if (trim($this->apiKey) === '') {
            throw new \InvalidArgumentException('CLOUDCONVERT_API_KEY is empty. Set it in .env.local.');
        }
    }
}
