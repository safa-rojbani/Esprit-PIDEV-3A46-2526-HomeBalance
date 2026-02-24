<?php

namespace App\DataFixtures;

use App\Entity\Family;
use App\Entity\FamilyInvitation;
use App\Entity\User;
use App\Enum\InvitationStatus;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InvitationFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Family $family */
        $family = $this->getReference(FamilyFixtures::DEMO_FAMILY, Family::class);
        /** @var User $parent */
        $parent = $this->getReference(UserFixtures::PARENT, User::class);

        $pending = (new FamilyInvitation())
            ->setFamily($family)
            ->setCreatedBy($parent)
            ->setInvitedEmail('pending_invite@example.com')
            ->setJoinCode('PEND001')
            ->setStatus(InvitationStatus::PENDING)
            ->setExpiresAt((new DateTime())->modify('+3 days'));

        $accepted = (new FamilyInvitation())
            ->setFamily($family)
            ->setCreatedBy($parent)
            ->setInvitedEmail('accepted_invite@example.com')
            ->setJoinCode('ACPT001')
            ->setStatus(InvitationStatus::ACCEPTED)
            ->setExpiresAt((new DateTime())->modify('-1 day'));

        $expired = (new FamilyInvitation())
            ->setFamily($family)
            ->setCreatedBy($parent)
            ->setInvitedEmail('expired_invite@example.com')
            ->setJoinCode('EXPR001')
            ->setStatus(InvitationStatus::EXPIRED)
            ->setExpiresAt((new DateTime())->modify('-2 days'));

        $manager->persist($pending);
        $manager->persist($accepted);
        $manager->persist($expired);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            FamilyFixtures::class,
        ];
    }
}
