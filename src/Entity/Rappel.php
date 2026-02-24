<?php

namespace App\Entity;

use App\Repository\RappelRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\RappelEvenement;

#[ORM\Entity(repositoryClass: RappelRepository::class)]
class Rappel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $offsetMinutes = null;

    #[ORM\Column(length: 255)]
    private ?string $canal = null;

    #[ORM\Column(nullable: true)]
    private ?bool $actif = null;

    #[ORM\Column]
    private ?bool $estLu = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?Family $family = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOffsetMinutes(): ?int
    {
        return $this->offsetMinutes;
    }

    public function setOffsetMinutes(int $offsetMinutes): static
    {
        $this->offsetMinutes = $offsetMinutes;

        return $this;
    }

    public function getCanal(): ?RappelEvenement
    {
        return $this->canal ? RappelEvenement::from($this->canal) : null;
    }

    public function setCanal(RappelEvenement $canal): static
    {
        $this->canal = $canal->value;

        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(?bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function isEstLu(): ?bool
    {
        return $this->estLu;
    }

    public function setEstLu(bool $estLu): static
    {
        $this->estLu = $estLu;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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
}
