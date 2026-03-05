<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\TaskDifficulty;
use App\Enum\TaskRecurrence;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\TaskCompletion;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(min: 3, max: 255)]
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(min: 5)]
    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[Assert\NotNull]
    #[ORM\Column(length: 50)]
    private ?string $difficulty = null;

    #[Assert\NotNull]
    #[ORM\Column(length: 50)]
    private ?string $recurrence = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Family $family = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    // ✅ RELATION AVEC LES VALIDATIONS
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskCompletion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['completedAt' => 'DESC'])]

    private Collection $taskCompletions;

    public function __construct()
    {
        $this->taskCompletions = new ArrayCollection();
    }

    /* =======================
        GETTERS / SETTERS
    ======================== */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDifficulty(): ?TaskDifficulty
    {
        return $this->difficulty ? TaskDifficulty::from($this->difficulty) : null;
    }

    public function setDifficulty(TaskDifficulty $difficulty): static
    {
        $this->difficulty = $difficulty->value;
        return $this;
    }

    public function getRecurrence(): ?TaskRecurrence
    {
        return $this->recurrence ? TaskRecurrence::from($this->recurrence) : null;
    }

    public function setRecurrence(TaskRecurrence $recurrence): static
    {
        $this->recurrence = $recurrence->value;
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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    // 🔑 POUR LE FRONT (TWIG)
    public function getTaskCompletions(): Collection
    {
        return $this->taskCompletions;
    }
}
    
