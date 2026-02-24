<?php

namespace App\Entity;

use App\Repository\FamilyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FamilyRepository::class)]
class Family
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $joinCode = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $codeExpiresAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, FamilyMembership>
     */
    #[ORM\OneToMany(mappedBy: 'family', targetEntity: FamilyMembership::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $memberships;

    /**
     * @var Collection<int, FamilyBadge>
     */
    #[ORM\OneToMany(mappedBy: 'family', targetEntity: FamilyBadge::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $badges;

    public function __construct()
    {
        $this->memberships = new ArrayCollection();
        $this->badges = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getJoinCode(): ?string
    {
        return $this->joinCode;
    }

    public function setJoinCode(?string $joinCode): static
    {
        $this->joinCode = $joinCode;

        return $this;
    }

    public function getCodeExpiresAt(): ?\DateTime
    {
        return $this->codeExpiresAt;
    }

    public function setCodeExpiresAt(?\DateTime $codeExpiresAt): static
    {
        $this->codeExpiresAt = $codeExpiresAt;

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, FamilyMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(FamilyMembership $membership): static
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
        }

        return $this;
    }

    public function removeMembership(FamilyMembership $membership): static
    {
        $this->memberships->removeElement($membership);

        return $this;
    }

    /**
     * @return Collection<int, FamilyBadge>
     */
    public function getBadges(): Collection
    {
        return $this->badges;
    }

    public function addBadge(FamilyBadge $badge): static
    {
        if (!$this->badges->contains($badge)) {
            $this->badges->add($badge);
        }

        return $this;
    }

    public function removeBadge(FamilyBadge $badge): static
    {
        $this->badges->removeElement($badge);

        return $this;
    }
}
