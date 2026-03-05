<?php

namespace App\Entity\ModuleEvenement;

use App\Entity\Evenement;
use App\Entity\User;
use App\Repository\InvitationRsvpRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: InvitationRsvpRepository::class)]
#[ORM\Table(name: 'invitation_rsvp')]
#[ORM\UniqueConstraint(columns: ['evenement_id', 'invitee_id'])]
class InvitationRsvp
{
    public const STATUS_EN_ATTENTE = 'en_attente';
    public const STATUS_ACCEPTE = 'accepte';
    public const STATUS_REFUSE = 'refuse';
    public const STATUS_PEUT_ETRE = 'peut_etre';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Ignore]
    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUS_EN_ATTENTE;

    #[ORM\ManyToOne(inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $invitee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $invitedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reponduAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->token = bin2hex(random_bytes(32));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(#[\SensitiveParameter] string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;
        return $this;
    }

    public function getInvitee(): ?User
    {
        return $this->invitee;
    }

    public function setInvitee(?User $invitee): static
    {
        $this->invitee = $invitee;
        return $this;
    }

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function setInvitedBy(?User $invitedBy): static
    {
        $this->invitedBy = $invitedBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getReponduAt(): ?\DateTimeImmutable
    {
        return $this->reponduAt;
    }

    public function setReponduAt(?\DateTimeImmutable $reponduAt): static
    {
        $this->reponduAt = $reponduAt;
        return $this;
    }
}
