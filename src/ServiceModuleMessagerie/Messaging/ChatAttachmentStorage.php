<?php

declare(strict_types=1);

namespace App\ServiceModuleMessagerie\Messaging;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ChatAttachmentStorage
{
    private const DIRECTORY = 'public/uploads/chat-attachments';

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * Store an uploaded chat attachment and return its public-relative path.
     */
    public function store(UploadedFile $file): string
    {
        $targetDirectory = $this->projectDir . '/' . self::DIRECTORY;
        $this->filesystem->mkdir($targetDirectory);

        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s.%s', bin2hex(random_bytes(12)), $extension);

        $file->move($targetDirectory, $filename);

        // Path relative to /public, suitable for asset() or direct linking
        return 'uploads/chat-attachments/' . $filename;
    }
}

