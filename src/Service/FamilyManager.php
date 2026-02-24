<?php

namespace App\Service;

use App\Entity\Family;
use App\Entity\FamilyInvitation;
use App\Entity\FamilyMembership;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\InvitationStatus;
use App\Repository\FamilyInvitationRepository;
use App\Repository\FamilyMembershipRepository;
use App\Repository\FamilyRepository;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

final class FamilyManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FamilyRepository $familyRepository,
        private readonly FamilyInvitationRepository $invitationRepository,
        private readonly FamilyMembershipRepository $membershipRepository,
    ) {
    }

    public function createFamily(User $user, string $name): Family
    {
        if ($user->getFamily() !== null) {
            throw new LogicException('You already belong to a family.');
        }

        $family = (new Family())
            ->setName($name)
            ->setCreatedAt(new DateTimeImmutable())
            ->setCreatedBy($user);

        $this->assignJoinCode($family);
        $this->entityManager->persist($family);
        $this->attachUser($user, $family, FamilyRole::PARENT);
        $this->entityManager->flush();

        return $family;
    }

    public function generateJoinCode(Family $family): Family
    {
        $this->assignJoinCode($family);
        $family->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        return $family;
    }

    public function inviteByEmail(Family $family, User $creator, string $email): FamilyInvitation
    {
        if ($creator->getFamily()?->getId() !== $family->getId() || $creator->getFamilyRole() !== FamilyRole::PARENT) {
            throw new LogicException('Only a family admin can send invitations.');
        }

        $invitation = (new FamilyInvitation())
            ->setFamily($family)
            ->setCreatedBy($creator)
            ->setInvitedEmail($email)
            ->setJoinCode($this->generateCode(10))
            ->setExpiresAt((new DateTime())->add(new DateInterval('P7D')))
            ->setStatus(InvitationStatus::PENDING);

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        return $invitation;
    }

    public function joinFamilyByCode(User $user, string $code): Family
    {
        if ($user->getFamily() !== null) {
            throw new LogicException('Leave your current family before joining another.');
        }

        $invitation = $this->invitationRepository->findActiveByJoinCode($code);
        if ($invitation !== null) {
            if ($invitation->getInvitedEmail() && strtolower($invitation->getInvitedEmail()) !== strtolower((string) $user->getEmail())) {
                throw new LogicException('This invitation was sent to a different email address.');
            }

            $family = $invitation->getFamily();
            $this->attachUser($user, $family, FamilyRole::CHILD);
            $invitation->setStatus(InvitationStatus::ACCEPTED);
            $invitation->setExpiresAt(new DateTime());
            $this->entityManager->flush();

            return $family;
        }

        $family = $this->familyRepository->findOneActiveByJoinCode($code);
        if ($family === null) {
            throw new LogicException('No active family found for that code.');
        }

        $this->attachUser($user, $family, FamilyRole::CHILD);
        $this->entityManager->flush();

        return $family;
    }

    private function attachUser(User $user, Family $family, FamilyRole $role): void
    {
        $activeMembership = $this->membershipRepository->findActiveMembershipForUser($user);
        if ($activeMembership !== null) {
            $activeMembership->leave();
        }

        $membership = new FamilyMembership($family, $user, $role);
        $this->entityManager->persist($membership);

        $user->setFamily($family);
        $user->setFamilyRole($role);
        $user->setUpdatedAt(new DateTimeImmutable());
    }

    private function assignJoinCode(Family $family): void
    {
        $family->setJoinCode(strtoupper($this->generateCode(6)));
        $family->setCodeExpiresAt((new DateTime())->add(new DateInterval('P7D')));
    }

    private function generateCode(int $length): string
    {
        return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes($length))), 0, $length);
    }
}
