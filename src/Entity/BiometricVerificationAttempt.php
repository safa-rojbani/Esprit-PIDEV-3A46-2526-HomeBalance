<?php

namespace App\Entity;

use App\Repository\BiometricVerificationAttemptRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BiometricVerificationAttemptRepository::class)]
class BiometricVerificationAttempt
{
    public const RESULT_PASSED = 'PASSED';
    public const RESULT_FAILED = 'FAILED';
    public const RESULT_FALLBACK_REQUIRED = 'FALLBACK_REQUIRED';
    public const RESULT_ERROR = 'ERROR';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $actorUser = null;

    #[ORM\Column(length: 128)]
    private string $actionKey = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $targetUser = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $similarityScore = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $thresholdUsed = 82.0;

    #[ORM\Column(length: 32)]
    private string $result = self::RESULT_ERROR;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $providerResponseMeta = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
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

    public function getActionKey(): string
    {
        return $this->actionKey;
    }

    public function setActionKey(string $actionKey): self
    {
        $this->actionKey = $actionKey;

        return $this;
    }

    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }

    public function setTargetUser(?User $targetUser): self
    {
        $this->targetUser = $targetUser;

        return $this;
    }

    public function getSimilarityScore(): ?float
    {
        return $this->similarityScore;
    }

    public function setSimilarityScore(?float $similarityScore): self
    {
        $this->similarityScore = $similarityScore;

        return $this;
    }

    public function getThresholdUsed(): float
    {
        return $this->thresholdUsed;
    }

    public function setThresholdUsed(float $thresholdUsed): self
    {
        $this->thresholdUsed = $thresholdUsed;

        return $this;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function setResult(string $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getProviderResponseMeta(): ?array
    {
        return $this->providerResponseMeta;
    }

    public function setProviderResponseMeta(?array $providerResponseMeta): self
    {
        $this->providerResponseMeta = $providerResponseMeta;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

