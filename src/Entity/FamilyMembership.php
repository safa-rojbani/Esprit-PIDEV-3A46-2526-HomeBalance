<?php

namespace App\Entity;

use App\Enum\FamilyRole;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'family_memberships')]
#[ORM\UniqueConstraint(columns: ['family_id', 'user_id'])]
final class FamilyMembership
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    private Family $family;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 32)]
    private string $role;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $leftAt = null;

    public function __construct(Family $family, User $user, FamilyRole $role)
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->family = $family;
        $this->user = $user;
        $this->role = $role->value;
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getFamily(): Family
    {
        return $this->family;
    }

    public function setFamily(Family $family): void
    {
        $this->family = $family;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getRole(): FamilyRole
    {
        return FamilyRole::from($this->role);
    }

    public function setRole(FamilyRole $role): void
    {
        $this->role = $role->value;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeImmutable $joinedAt): void
    {
        $this->joinedAt = $joinedAt;
    }

    public function getLeftAt(): ?\DateTimeImmutable
    {
        return $this->leftAt;
    }

    public function setLeftAt(?\DateTimeImmutable $leftAt): void
    {
        $this->leftAt = $leftAt;
    }

    public function leave(): void
    {
        $this->leftAt = new \DateTimeImmutable();
    }
}
