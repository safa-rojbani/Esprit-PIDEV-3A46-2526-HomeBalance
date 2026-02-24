<?php

namespace App\Entity;

use App\Repository\AdminAiSessionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AdminAiSessionRepository::class)]
class AdminAiSession
{
    public const STATUS_PLANNED = 'PLANNED';
    public const STATUS_DRY_RUN_READY = 'DRY_RUN_READY';
    public const STATUS_EXECUTED = 'EXECUTED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $actorUser = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $rawPrompt = '';

    #[ORM\Column(type: Types::JSON)]
    private array $normalizedIntent = [];

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_PLANNED;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $dryRunSnapshot = null;

    #[ORM\Column]
    private bool $requiresStepUp = false;

    #[ORM\Column]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->expiresAt = (new DateTimeImmutable())->modify('+30 minutes');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getActorUser(): ?User
    {
        return $this->actorUser;
    }

    public function setActorUser(User $actorUser): self
    {
        $this->actorUser = $actorUser;

        return $this;
    }

    public function getRawPrompt(): string
    {
        return $this->rawPrompt;
    }

    public function setRawPrompt(string $rawPrompt): self
    {
        $this->rawPrompt = $rawPrompt;

        return $this;
    }

    public function getNormalizedIntent(): array
    {
        return $this->normalizedIntent;
    }

    public function setNormalizedIntent(array $normalizedIntent): self
    {
        $this->normalizedIntent = $normalizedIntent;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getDryRunSnapshot(): ?array
    {
        return $this->dryRunSnapshot;
    }

    public function setDryRunSnapshot(?array $dryRunSnapshot): self
    {
        $this->dryRunSnapshot = $dryRunSnapshot;

        return $this;
    }

    public function isRequiresStepUp(): bool
    {
        return $this->requiresStepUp;
    }

    public function setRequiresStepUp(bool $requiresStepUp): self
    {
        $this->requiresStepUp = $requiresStepUp;

        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }
}

