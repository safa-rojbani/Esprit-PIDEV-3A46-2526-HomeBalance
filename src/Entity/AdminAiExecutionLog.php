<?php

namespace App\Entity;

use App\Repository\AdminAiExecutionLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminAiExecutionLogRepository::class)]
class AdminAiExecutionLog
{
    public const RESULT_SUCCESS = 'SUCCESS';
    public const RESULT_PARTIAL = 'PARTIAL';
    public const RESULT_FAILED = 'FAILED';
    public const RESULT_BLOCKED = 'BLOCKED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AdminAiSession $session = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $actorUser = null;

    #[ORM\Column(length: 32)]
    private string $result = self::RESULT_FAILED;

    #[ORM\Column]
    private int $executedActionsCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorSummary = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?AdminAiSession
    {
        return $this->session;
    }

    public function setSession(AdminAiSession $session): self
    {
        $this->session = $session;

        return $this;
    }

    public function getActorUser(): ?User
    {
        return $this->actorUser;
    }

    public function setActorUser(User $actorUser): self
    {
        $this->actorUser = $actorUser;

        return $this;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function setResult(string $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getExecutedActionsCount(): int
    {
        return $this->executedActionsCount;
    }

    public function setExecutedActionsCount(int $executedActionsCount): self
    {
        $this->executedActionsCount = $executedActionsCount;

        return $this;
    }

    public function getErrorSummary(): ?string
    {
        return $this->errorSummary;
    }

    public function setErrorSummary(?string $errorSummary): self
    {
        $this->errorSummary = $errorSummary;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

