<?php

namespace App\Entity;

use App\Repository\HistoriqueAchatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HistoriqueAchatRepository::class)]
class HistoriqueAchat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montantAchete = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantiteAchete = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateAchat = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Achat $achat = null;

    #[ORM\ManyToOne]
    private ?Revenu $revenu = null;

    #[ORM\ManyToOne]
    private ?Family $family = null;

    #[ORM\ManyToOne]
    private ?User $paidBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMontantAchete(): ?string
    {
        return $this->montantAchete;
    }

    public function setMontantAchete(string $montantAchete): static
    {
        $this->montantAchete = $montantAchete;

        return $this;
    }

    public function getQuantiteAchete(): ?int
    {
        return $this->quantiteAchete;
    }

    public function setQuantiteAchete(?int $quantiteAchete): static
    {
        $this->quantiteAchete = $quantiteAchete;

        return $this;
    }

    public function getDateAchat(): ?\DateTimeImmutable
    {
        return $this->dateAchat;
    }

    public function setDateAchat(\DateTimeImmutable $dateAchat): static
    {
        $this->dateAchat = $dateAchat;

        return $this;
    }

    public function getAchat(): ?Achat
    {
        return $this->achat;
    }

    public function setAchat(?Achat $achat): static
    {
        $this->achat = $achat;

        return $this;
    }

    public function getRevenu(): ?Revenu
    {
        return $this->revenu;
    }

    public function setRevenu(?Revenu $revenu): static
    {
        $this->revenu = $revenu;

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

    public function getPaidBy(): ?User
    {
        return $this->paidBy;
    }

    public function setPaidBy(?User $paidBy): static
    {
        $this->paidBy = $paidBy;

        return $this;
    }
}
