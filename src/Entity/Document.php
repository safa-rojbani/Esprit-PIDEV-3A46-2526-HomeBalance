<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\EtatDocument;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[UniqueEntity(
    fields: ['family', 'fileName'],
    message: 'Un document avec ce nom existe deja dans votre famille.'
)]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le nom du fichier ne peut pas depasser {{ limit }} caracteres.'
    )]
    private ?string $fileName = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le chemin du fichier ne peut pas depasser {{ limit }} caracteres.'
    )]
    private ?string $filePath = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le type du fichier ne peut pas depasser {{ limit }} caracteres.'
    )]
    private ?string $fileType = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'La taille du fichier doit etre une valeur numerique.'
    )]
    private ?string $filesize = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $etat = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne]
    private ?Family $family = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $uploadedBY = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Gallery $gallery = null;

    #[Assert\NotNull(message: 'Veuillez selectionner un fichier.', groups: ['document_create'])]
    #[Assert\File(
        maxSize: '20M',
        maxSizeMessage: 'Le fichier ne doit pas depasser 20 Mo.',
        mimeTypes: [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'video/ogg',
            'video/x-matroska',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/zip',
            'application/x-zip-compressed'
        ],
        mimeTypesMessage: 'Type de fichier non autorise.'
    )]
    private ?UploadedFile $file = null;

    public function getGallery(): ?Gallery
    {
        return $this->gallery;
    }

    public function setGallery(?Gallery $gallery): static
    {
        $this->gallery = $gallery;
        return $this;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): static
    {
        $this->file = $file;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getFilePath(): ?string
    {
        if ($this->filePath === null) {
            return null;
        }

        return ltrim($this->filePath, '/');
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = ltrim($filePath, '/');

        return $this;
    }

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    public function setFileType(string $fileType): static
    {
        $this->fileType = $fileType;

        return $this;
    }

    public function getFilesize(): ?string
    {
        return $this->filesize;
    }

    public function setFilesize(?string $filesize): static
    {
        $this->filesize = $filesize;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(?\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getEtat(): ?EtatDocument
    {
        return $this->etat ? EtatDocument::from($this->etat) : null;
    }

    public function setEtat(EtatDocument $etat): static
    {
        $this->etat = $etat->value;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getFamily(): ?Family
    {
        return $this->family;
    }

    public function setFamily(?Family $family): static
    {
        $this->family = $family;

        return $this;
    }

    public function getUploadedBY(): ?User
    {
        return $this->uploadedBY;
    }

    public function setUploadedBY(?User $uploadedBY): static
    {
        $this->uploadedBY = $uploadedBY;

        return $this;
    }
}
