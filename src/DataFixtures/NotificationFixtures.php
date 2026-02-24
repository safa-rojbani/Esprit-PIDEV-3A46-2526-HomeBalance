<?php

namespace App\DataFixtures;

use App\Entity\AccountNotification;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class NotificationFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var User $parent */
        $parent = $this->getReference(UserFixtures::PARENT, User::class);

        $pending = (new AccountNotification())
            ->setUser($parent)
            ->setKey('verify_email')
            ->setChannel('email')
            ->setStatus('PENDING')
            ->setPayload(['token' => 'demo-token'])
            ->setAttempts(0)
            ->setCreatedAt(new DateTimeImmutable());

        $sent = (new AccountNotification())
            ->setUser($parent)
            ->setKey('password_reset')
            ->setChannel('email')
            ->setStatus('SENT')
            ->setAttempts(1)
            ->setCreatedAt((new DateTimeImmutable())->modify('-1 day'))
            ->setSentAt((new DateTimeImmutable())->modify('-1 day'));

        $failed = (new AccountNotification())
            ->setUser($parent)
            ->setKey('family_update')
            ->setChannel('email')
            ->setStatus('FAILED')
            ->setAttempts(3)
            ->setLastError('Mailbox unavailable')
            ->setCreatedAt((new DateTimeImmutable())->modify('-2 days'));

        $manager->persist($pending);
        $manager->persist($sent);
        $manager->persist($failed);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
