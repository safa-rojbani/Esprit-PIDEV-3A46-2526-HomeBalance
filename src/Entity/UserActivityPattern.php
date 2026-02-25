<?php

namespace App\Entity;

use App\Repository\UserActivityPatternRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserActivityPatternRepository::class)]
class UserActivityPattern
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $peakHours = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastCalculatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPeakHours(): ?array
    {
        return $this->peakHours;
    }

    public function setPeakHours(?array $peakHours): static
    {
        $this->peakHours = $peakHours;

        return $this;
    }

    public function getLastCalculatedAt(): ?\DateTimeImmutable
    {
        return $this->lastCalculatedAt;
    }

    public function setLastCalculatedAt(?\DateTimeImmutable $lastCalculatedAt): static
    {
        $this->lastCalculatedAt = $lastCalculatedAt;

        return $this;
    }
}
