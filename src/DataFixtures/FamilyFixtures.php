<?php

namespace App\DataFixtures;

use App\Entity\Family;
use App\Entity\FamilyMembership;
use App\Entity\User;
use App\Enum\FamilyRole;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class FamilyFixtures extends Fixture implements DependentFixtureInterface
{
    public const DEMO_FAMILY = 'family.demo';

    public function load(ObjectManager $manager): void
    {
        /** @var User $parent */
        $parent = $this->getReference(UserFixtures::PARENT, User::class);
        /** @var User $child */
        $child = $this->getReference(UserFixtures::CHILD, User::class);

        $family = (new Family())
            ->setName('Demo Household')
            ->setJoinCode('DEMO24')
            ->setCodeExpiresAt((new DateTime())->modify('+7 days'))
            ->setCreatedAt(new DateTimeImmutable())
            ->setUpdatedAt(new DateTimeImmutable())
            ->setCreatedBy($parent);

        $parentMembership = new FamilyMembership($family, $parent, FamilyRole::PARENT);
        $childMembership = new FamilyMembership($family, $child, FamilyRole::CHILD);

        $parent->setFamily($family)->setFamilyRole(FamilyRole::PARENT);
        $child->setFamily($family)->setFamilyRole(FamilyRole::CHILD);

        $manager->persist($family);
        $manager->persist($parentMembership);
        $manager->persist($childMembership);
        $manager->flush();

        $this->addReference(self::DEMO_FAMILY, $family);
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
