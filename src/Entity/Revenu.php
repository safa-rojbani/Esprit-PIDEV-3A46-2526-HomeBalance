<?php

namespace App\Entity;

use App\Repository\RevenuRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity(repositoryClass: RevenuRepository::class)]
class Revenu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type de revenu est obligatoire.')]
     #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le type doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le type ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $typeRevenu = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
     #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
      #[Assert\Positive(message: 'Le montant doit être supérieur à 0.')]
    private ?string $montant = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $montantTotal = null;

    #[ORM\Column(nullable: true)]
     #[Assert\NotNull(message: 'La date du revenu est obligatoire.')]
         #[Assert\LessThanOrEqual('today', message: 'La date ne peut pas être dans le futur.')]
    private ?\DateTimeImmutable $dateRevenu = null;

    #[ORM\ManyToOne]
#[ORM\JoinColumn(nullable: true)]
private ?Family $family = null;

#[ORM\ManyToOne]
#[ORM\JoinColumn(nullable: true)]
private ?User $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeRevenu(): ?string
    {
        return $this->typeRevenu;
    }

    public function setTypeRevenu(?string $typeRevenu): static
    {
        $this->typeRevenu = $typeRevenu;

        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(?string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(?string $montantTotal): static
    {
        $this->montantTotal = $montantTotal;

        return $this;
    }

    public function getDateRevenu(): ?\DateTimeImmutable
    {
        return $this->dateRevenu;
    }

    public function setDateRevenu(?\DateTimeImmutable $dateRevenu): static
    {
        $this->dateRevenu = $dateRevenu;

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
