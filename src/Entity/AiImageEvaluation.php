<?php

namespace App\Entity;

use App\Enum\AiEvaluationDecision;
use App\Enum\AiEvaluationStatus;
use App\Repository\AiImageEvaluationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiImageEvaluationRepository::class)]
class AiImageEvaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'aiEvaluation')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TaskCompletion $completion = null;

    #[ORM\Column(length: 32)]
    private ?string $provider = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 16)]
    private ?string $status = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $decision = null;

    #[ORM\Column(nullable: true)]
    private ?int $tidyScore = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $confidence = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reasonShort = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rawResponse = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompletion(): ?TaskCompletion
    {
        return $this->completion;
    }

    public function setCompletion(TaskCompletion $completion): static
    {
        $this->completion = $completion;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getStatus(): ?AiEvaluationStatus
    {
        return $this->status !== null ? AiEvaluationStatus::from($this->status) : null;
    }

    public function setStatus(AiEvaluationStatus $status): static
    {
        $this->status = $status->value;

        return $this;
    }

    public function getDecision(): ?AiEvaluationDecision
    {
        return $this->decision !== null ? AiEvaluationDecision::from($this->decision) : null;
    }

    public function setDecision(?AiEvaluationDecision $decision): static
    {
        $this->decision = $decision?->value;

        return $this;
    }

    public function getTidyScore(): ?int
    {
        return $this->tidyScore;
    }

    public function setTidyScore(?int $tidyScore): static
    {
        $this->tidyScore = $tidyScore;

        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): static
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getReasonShort(): ?string
    {
        return $this->reasonShort;
    }

    public function setReasonShort(?string $reasonShort): static
    {
        $this->reasonShort = $reasonShort;

        return $this;
    }

    public function getRawResponse(): ?string
    {
        return $this->rawResponse;
    }

    public function setRawResponse(?string $rawResponse): static
    {
        $this->rawResponse = $rawResponse;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

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

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }
}

