<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ChatAttachmentStorage
{
    public function __construct(private
        string $chatAttachmentsDirectory, private
        SluggerInterface $slugger
        )
    {
    }

    public function store(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $fileName = $safeFilename . '-' . uniqid() . '.' . $extension;

        try {
            if (!is_dir($this->chatAttachmentsDirectory)) {
                mkdir($this->chatAttachmentsDirectory, 0777, true);
            }
            $file->move($this->chatAttachmentsDirectory, $fileName);
        }
        catch (FileException $e) {
            throw new \RuntimeException('Could not save file: ' . $e->getMessage());
        }

        return 'uploads/chat/' . $fileName;
    }

    public function getPublicPath(string $filename): string
    {
        return 'uploads/chat/' . $filename;
    }
}
