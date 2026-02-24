<?php

namespace App\DataFixtures;

use App\Entity\Badge;
use App\Enum\BadgeCode;
use App\Enum\BadgeScope;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BadgeFixtures extends Fixture
{
    public const HARDWORKING_MEMBER = 'badge.hardworking_member';
    public const BALANCED_FAMILY = 'badge.balanced_family';
    public const HARDWORKING_FAMILY = 'badge.hardworking_family';

    public function load(ObjectManager $manager): void
    {
        $member = (new Badge())
            ->setName('Hardworking Member')
            ->setCode(BadgeCode::HARDWORKING_MEMBER->value)
            ->setScope(BadgeScope::USER)
            ->setDescription('Highest contributing member in the family.')
            ->setIcon('bx-star')
            ->setRequiredPoints(1);
        $manager->persist($member);
        $this->addReference(self::HARDWORKING_MEMBER, $member);

        $balanced = (new Badge())
            ->setName('Balanced Family')
            ->setCode(BadgeCode::BALANCED_FAMILY->value)
            ->setScope(BadgeScope::FAMILY)
            ->setDescription('Family with balanced points spread.')
            ->setIcon('bx-balance')
            ->setRequiredPoints(1);
        $manager->persist($balanced);
        $this->addReference(self::BALANCED_FAMILY, $balanced);

        $hardworkingFamily = (new Badge())
            ->setName('Hardworking Family')
            ->setCode(BadgeCode::HARDWORKING_FAMILY->value)
            ->setScope(BadgeScope::FAMILY)
            ->setDescription('Top scoring family.')
            ->setIcon('bx-trophy')
            ->setRequiredPoints(1);
        $manager->persist($hardworkingFamily);
        $this->addReference(self::HARDWORKING_FAMILY, $hardworkingFamily);

        $manager->flush();
    }
}
