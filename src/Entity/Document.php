<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(length: 255)]
    private ?string $filePath = null;

    #[ORM\Column(length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(length: 10)]
    private ?string $extension = null;

    #[ORM\Column]
    private ?float $fileSize = null;

    #[ORM\Column(length: 255)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $isPublic = null;

    #[ORM\Column(length: 255)]
    private ?string $etat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileHash = null;

    #[ORM\Column(nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $uploadedBY = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Gallery $gallery = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Family $family = null;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: DocumentShare::class, orphanRemoval: true)]
    private Collection $documentShares;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: DocumentActivityLog::class, orphanRemoval: true)]
    private Collection $documentActivityLogs;

    public function __construct()
    {
        $this->documentShares = new ArrayCollection();
        $this->documentActivityLogs = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->isPublic = false;
        $this->etat = 'active';
    }

    /* Compatibility methods */

    public function getFileType(): ?string
    {
        return $this->mimeType;
    }

    public function setFileType(?string $fileType): static
    {
        $this->mimeType = $fileType;
        return $this;
    }

    public function getFilesize(): ?float
    {
        return $this->fileSize;
    }

    public function setFilesize(?float $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
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

    /* Standard getters/setters */

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
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): static
    {
        $this->extension = $extension;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setUploadedAt(\DateTimeImmutable $createdAt): static
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->etat;
    }

    public function setStatus(string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    public function setFileHash(?string $fileHash): static
    {
        $this->fileHash = $fileHash;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

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

    public function getUploadedBY(): ?User
    {
        return $this->uploadedBY;
    }

    public function setUploadedBY(?User $uploadedBY): static
    {
        $this->uploadedBY = $uploadedBY;

        return $this;
    }

    public function getGallery(): ?Gallery
    {
        return $this->gallery;
    }

    public function setGallery(?Gallery $gallery): static
    {
        $this->gallery = $gallery;

        return $this;
    }

    /**
     * @return Collection<int, DocumentShare>
     */
    public function getDocumentShares(): Collection
    {
        return $this->documentShares;
    }

    public function addDocumentShare(DocumentShare $documentShare): static
    {
        if (!$this->documentShares->contains($documentShare)) {
            $this->documentShares->add($documentShare);
            $documentShare->setDocument($this);
        }

        return $this;
    }

    public function removeDocumentShare(DocumentShare $documentShare): static
    {
        if ($this->documentShares->removeElement($documentShare)) {
            // set the owning side to null (unless already changed)
            if ($documentShare->getDocument() === $this) {
                $documentShare->setDocument(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DocumentActivityLog>
     */
    public function getDocumentActivityLogs(): Collection
    {
        return $this->documentActivityLogs;
    }

    public function addDocumentActivityLog(DocumentActivityLog $documentActivityLog): static
    {
        if (!$this->documentActivityLogs->contains($documentActivityLog)) {
            $this->documentActivityLogs->add($documentActivityLog);
            $documentActivityLog->setDocument($this);
        }

        return $this;
    }

    public function removeDocumentActivityLog(DocumentActivityLog $documentActivityLog): static
    {
        if ($this->documentActivityLogs->removeElement($documentActivityLog)) {
            // set the owning side to null (unless already changed)
            if ($documentActivityLog->getDocument() === $this) {
                $documentActivityLog->setDocument(null);
            }
        }

        return $this;
    }
}
