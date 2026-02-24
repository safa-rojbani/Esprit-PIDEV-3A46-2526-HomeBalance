<?php

namespace App\Entity;

use App\Repository\FamilyInvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\InvitationStatus;

#[ORM\Entity(repositoryClass: FamilyInvitationRepository::class)]
class FamilyInvitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $invitedEmail = null;

    #[ORM\Column(length: 255)]
    private ?string $joinCode = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $expiresAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Family $family = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvitedEmail(): ?string
    {
        return $this->invitedEmail;
    }

    public function setInvitedEmail(?string $invitedEmail): static
    {
        $this->invitedEmail = $invitedEmail;

        return $this;
    }

    public function getJoinCode(): ?string
    {
        return $this->joinCode;
    }

    public function setJoinCode(string $joinCode): static
    {
        $this->joinCode = $joinCode;

        return $this;
    }

    public function getStatus(): ?InvitationStatus
    {
        return $this->status ? InvitationStatus::from($this->status) : null;
    }

    public function setStatus(InvitationStatus $status): static
    {
        $this->status = $status->value;

        return $this;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
