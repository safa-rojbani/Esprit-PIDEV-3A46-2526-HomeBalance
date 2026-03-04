<?php

namespace App\Entity;

use App\Repository\ConversationParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversationParticipantRepository::class)]
class ConversationParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $joinedAt = null;

    #[ORM\ManyToOne(inversedBy: "conversationParticipants")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(inversedBy: "conversationParticipants")]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeImmutable $joinedAt): static
    {
        $this->joinedAt = $joinedAt;

        return $this;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;

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
}
