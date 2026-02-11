<?php

namespace App\Entity;

use App\Repository\TaskAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\TaskAssignmentStatus;

#[ORM\Entity(repositoryClass: TaskAssignmentRepository::class)]
class TaskAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Date d’assignation
    #[ORM\Column]
    private ?\DateTimeImmutable $assignedAt = null;

    // Date limite (optionnelle)
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    // Date de refus (si l’enfant refuse)
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $refusedAt = null;

    // Tâche concernée
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Task $task = null;

    // Utilisateur assigné (enfant)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // Famille concernée
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Family $family = null;

    // Statut de l’assignation
    #[ORM\Column(length: 50)]
    private ?string $status = null;

    /* ==========================
        GETTERS & SETTERS
    ========================== */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssignedAt(): ?\DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(\DateTimeImmutable $assignedAt): static
    {
        $this->assignedAt = $assignedAt;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getRefusedAt(): ?\DateTimeImmutable
    {
        return $this->refusedAt;
    }

    public function setRefusedAt(?\DateTimeImmutable $refusedAt): static
    {
        $this->refusedAt = $refusedAt;
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

    public function getFamily(): ?Family
    {
        return $this->family;
    }

    public function setFamily(Family $family): static
    {
        $this->family = $family;
        return $this;
    }

    public function getStatus(): ?TaskAssignmentStatus
    {
        return $this->status ? TaskAssignmentStatus::from($this->status) : null;
    }

    public function setStatus(TaskAssignmentStatus $status): static
    {
        $this->status = $status->value;
        return $this;
    }
}
