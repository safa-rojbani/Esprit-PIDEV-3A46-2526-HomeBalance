<?php

namespace App\Entity;

use App\Repository\TaskCompletionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskCompletionRepository::class)]
class TaskCompletion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Quand l’enfant soumet la tâche
    #[ORM\Column]
    private ?\DateTimeImmutable $completedAt = null;

    // Chemin de la photo
    #[ORM\Column(length: 255)]
    private ?string $proof = null;

    // Décision du parent
    #[ORM\Column(nullable: true)]
    private ?bool $isValidated = null;

    // Quand le parent décide
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    // Tâche concernée
    #[ORM\ManyToOne(inversedBy: 'taskCompletions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Task $task = null;

    // Enfant
    #[ORM\ManyToOne(inversedBy: 'taskCompletions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // Parent validateur
    #[ORM\ManyToOne(inversedBy: 'taskCompletions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $validatedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
private ?string $parentComment = null;


    /* =====================
        GETTERS / SETTERS
    ====================== */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getProof(): ?string
    {
        return $this->proof;
    }

    public function setProof(string $proof): static
    {
        $this->proof = $proof;
        return $this;
    }

    public function isValidated(): ?bool
    {
        return $this->isValidated;
    }

    public function setIsValidated(?bool $isValidated): static
    {
        $this->isValidated = $isValidated;
        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(Task $task): static
    {
        $this->task = $task;
        return $this;
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

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
        return $this;
    }
    public function getParentComment(): ?string
{
    return $this->parentComment;
}

public function setParentComment(?string $parentComment): static
{
    $this->parentComment = $parentComment;
    return $this;
}

}
