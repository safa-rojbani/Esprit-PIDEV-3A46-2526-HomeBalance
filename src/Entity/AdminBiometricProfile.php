<?php

namespace App\Entity;

use App\Repository\AdminBiometricProfileRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AdminBiometricProfileRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_admin_biometric_user', fields: ['user'])]
class AdminBiometricProfile
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 32)]
    private string $provider = 'luxand';

    #[ORM\Column(length: 512)]
    private string $referenceFaceTokenEncrypted = '';

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private ?DateTimeImmutable $consentAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getReferenceFaceTokenEncrypted(): string
    {
        return $this->referenceFaceTokenEncrypted;
    }

    public function setReferenceFaceTokenEncrypted(string $referenceFaceTokenEncrypted): self
    {
        $this->referenceFaceTokenEncrypted = $referenceFaceTokenEncrypted;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getConsentAt(): ?DateTimeImmutable
    {
        return $this->consentAt;
    }

    public function setConsentAt(DateTimeImmutable $consentAt): self
    {
        $this->consentAt = $consentAt;

        return $this;
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
