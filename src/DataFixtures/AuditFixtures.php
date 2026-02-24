<?php

namespace App\DataFixtures;

use App\Entity\AuditTrail;
use App\Entity\Family;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AuditFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Family $family */
        $family = $this->getReference(FamilyFixtures::DEMO_FAMILY, Family::class);
        /** @var User $parent */
        $parent = $this->getReference(UserFixtures::PARENT, User::class);
        /** @var User $suspended */
        $suspended = $this->getReference(UserFixtures::SUSPENDED, User::class);

        $events = [
            ['user' => $parent, 'action' => 'user.login', 'days' => -1, 'payload' => ['ip' => '127.0.0.1']],
            ['user' => $parent, 'action' => 'user.password.changed', 'days' => -10, 'payload' => []],
            ['user' => $parent, 'action' => 'user.role.change.requested', 'days' => -2, 'payload' => ['requestedRole' => 'ADMIN']],
            ['user' => $suspended, 'action' => 'auth.login.failed', 'days' => -3, 'payload' => ['ip' => '127.0.0.2']],
        ];

        foreach ($events as $event) {
            $record = (new AuditTrail())
                ->setUser($event['user'])
                ->setFamily($family)
                ->setAction($event['action'])
                ->setPayload($event['payload'])
                ->setChannel('web')
                ->setIpAddress($event['payload']['ip'] ?? '127.0.0.1')
                ->setUserAgent('FixtureSeed')
                ->setCreatedAt((new DateTimeImmutable())->modify((string) $event['days'] . ' days'));
            $manager->persist($record);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            FamilyFixtures::class,
        ];
    }
}
