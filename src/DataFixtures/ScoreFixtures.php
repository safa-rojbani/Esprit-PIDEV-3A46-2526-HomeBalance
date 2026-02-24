<?php

namespace App\DataFixtures;

use App\Entity\Family;
use App\Entity\Score;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ScoreFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Family $family */
        $family = $this->getReference(FamilyFixtures::DEMO_FAMILY, Family::class);
        /** @var User $parent */
        $parent = $this->getReference(UserFixtures::PARENT, User::class);
        /** @var User $child */
        $child = $this->getReference(UserFixtures::CHILD, User::class);

        $parentScore = (new Score())
            ->setUser($parent)
            ->setFamily($family)
            ->setTotalPoints(120)
            ->setLastUpdated(new DateTimeImmutable());

        $childScore = (new Score())
            ->setUser($child)
            ->setFamily($family)
            ->setTotalPoints(95)
            ->setLastUpdated(new DateTimeImmutable());

        $manager->persist($parentScore);
        $manager->persist($childScore);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            FamilyFixtures::class,
        ];
    }
}
