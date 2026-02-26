<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserActivityPatternRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserActivityPatternRepository::class)]
class UserActivityPattern
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var list<int>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $peakHours = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $lastCalculatedAt = null;

    public function __construct()
    {
        $this->lastCalculatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getPeakHours(): array
    {
        return $this->peakHours;
    }

    /**
     * @param list<int> $peakHours
     */
    public function setPeakHours(array $peakHours): static
    {
        $this->peakHours = array_values($peakHours);

        return $this;
    }

    public function getLastCalculatedAt(): ?\DateTimeImmutable
    {
        return $this->lastCalculatedAt;
    }

    public function setLastCalculatedAt(\DateTimeImmutable $lastCalculatedAt): static
    {
        $this->lastCalculatedAt = $lastCalculatedAt;

        return $this;
    }
}

