<?php

namespace App\DTO;

use App\Entity\Family;
use App\Entity\User;
use App\Enum\FamilyRole;

final class FamilyMembershipInput
{
    public ?Family $family = null;
    public ?User $user = null;
    public ?FamilyRole $role = null;
    public ?\DateTimeImmutable $joinedAt = null;
    public ?\DateTimeImmutable $leftAt = null;
}
