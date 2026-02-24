<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AvatarStorage
{
    private const DIRECTORY = 'public/uploads/avatars';

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function store(UploadedFile $file): string
    {
        $targetDirectory = $this->projectDir . '/' . self::DIRECTORY;
        $this->filesystem->mkdir($targetDirectory);

        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s.%s', bin2hex(random_bytes(12)), $extension);

        $file->move($targetDirectory, $filename);

        return 'uploads/avatars/' . $filename;
    }

    public function remove(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $absolutePath = $this->projectDir . '/public/' . ltrim($relativePath, '/');
        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }
}
