<?php

namespace App\Entity;

use App\Enum\PrioritySupportTicket;
use App\Enum\StatusSupportTicket;
use App\Repository\SupportTicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportTicketRepository::class)]
class SupportTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $priority = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, SupportMessage> */
    #[ORM\OneToMany(targetEntity: SupportMessage::class, mappedBy: 'ticket', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = StatusSupportTicket::OPEN->value;
        $this->priority = PrioritySupportTicket::MEDIUM->value;
        $this->messages = new ArrayCollection();
    }

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

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): ?StatusSupportTicket
    {
        return $this->status ? StatusSupportTicket::from($this->status) : null;
    }

    public function setStatus(StatusSupportTicket $status): static
    {
        $this->status = $status->value;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPriority(): ?PrioritySupportTicket
    {
        return $this->priority ? PrioritySupportTicket::from($this->priority) : null;
    }

    public function setPriority(PrioritySupportTicket $priority): static
    {
        $this->priority = $priority->value;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, SupportMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(SupportMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setTicket($this);
        }

        return $this;
    }

    public function removeMessage(SupportMessage $message): static
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getTicket() === $this) {
                $message->setTicket(null);
            }
        }

        return $this;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->getStatus()) {
            StatusSupportTicket::OPEN => 'bg-label-danger',
            StatusSupportTicket::IN_PROGRESS => 'bg-label-warning',
            StatusSupportTicket::CLOSED => 'bg-label-success',
            default => 'bg-label-secondary',
        };
    }

    public function getPriorityBadgeClass(): string
    {
        return match ($this->getPriority()) {
            PrioritySupportTicket::HIGH => 'bg-label-danger',
            PrioritySupportTicket::MEDIUM => 'bg-label-warning',
            PrioritySupportTicket::LOW => 'bg-label-info',
            default => 'bg-label-secondary',
        };
    }
}
