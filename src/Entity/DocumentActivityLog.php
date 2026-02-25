<?php

namespace App\Entity;

use App\Repository\DocumentActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentActivityLogRepository::class)]
#[ORM\Table(name: 'document_activity_log')]
#[ORM\Index(name: 'idx_document_activity_family_created', columns: ['family_id', 'created_at'])]
#[ORM\Index(name: 'idx_document_activity_user_created', columns: ['user_id', 'created_at'])]
#[ORM\Index(name: 'idx_document_activity_document_created', columns: ['document_id', 'created_at'])]
#[ORM\Index(name: 'idx_document_activity_event_created', columns: ['event_type', 'created_at'])]
class DocumentActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Family $family = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Document $document = null;

    #[ORM\Column(length: 64)]
    private ?string $eventType = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $channel = null;

    #[ORM\Column(nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(?string $channel): static
    {
        $normalized = $channel !== null ? strtolower(trim($channel)) : null;
        $this->channel = $normalized !== '' ? $normalized : null;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}

