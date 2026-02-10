<?php

namespace App\Entity;
use Symfony\Component\Validator\Constraints as Assert;

use App\Repository\AchatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AchatRepository::class)]
class Achat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
     #[Assert\NotBlank(message: "Le nom de l'article est obligatoire.")]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: "Le nom doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le nom ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $nomArticle = null;

    #[ORM\Column]
    private ?bool $estAchete = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;
    #[Assert\NotNull(message: "Veuillez choisir une catégorie.")]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?CategorieAchat $categorie = null;

    #[ORM\ManyToOne]
    private ?Family $family = null;

    #[ORM\ManyToOne]
    private ?User $CreatedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomArticle(): ?string
    {
        return $this->nomArticle;
    }

    public function setNomArticle(string $nomArticle): static
    {
        $this->nomArticle = $nomArticle;

        return $this;
    }

    public function isEstAchete(): ?bool
    {
        return $this->estAchete;
    }

    public function setEstAchete(bool $estAchete): static
    {
        $this->estAchete = $estAchete;

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

    public function getCategorie(): ?CategorieAchat
    {
        return $this->categorie;
    }

    public function setCategorie(?CategorieAchat $categorie): static
    {
        $this->categorie = $categorie;

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
        return $this->CreatedBy;
    }

    public function setCreatedBy(?User $CreatedBy): static
    {
        $this->CreatedBy = $CreatedBy;

        return $this;
    }
}
