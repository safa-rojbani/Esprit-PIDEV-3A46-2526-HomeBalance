<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const ADMIN = 'user.admin';
    public const PARENT = 'user.parent';
    public const CHILD = 'user.child';
    public const INVITEE = 'user.invitee';
    public const SUSPENDED = 'user.suspended';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->buildUser(
            email: 'admin_demo@example.com',
            username: 'admin_demo',
            firstName: 'Admin',
            lastName: 'Demo',
            systemRole: SystemRole::ADMIN,
            familyRole: FamilyRole::SOLO,
            status: UserStatus::ACTIVE,
            roles: ['ROLE_ADMIN']
        );
        $this->addReference(self::ADMIN, $admin);
        $manager->persist($admin);

        $parent = $this->buildUser(
            email: 'parent_demo@example.com',
            username: 'parent_demo',
            firstName: 'Parent',
            lastName: 'Demo',
            systemRole: SystemRole::CUSTOMER,
            familyRole: FamilyRole::PARENT,
            status: UserStatus::ACTIVE
        );
        $this->addReference(self::PARENT, $parent);
        $manager->persist($parent);

        $child = $this->buildUser(
            email: 'child_demo@example.com',
            username: 'child_demo',
            firstName: 'Child',
            lastName: 'Demo',
            systemRole: SystemRole::CUSTOMER,
            familyRole: FamilyRole::CHILD,
            status: UserStatus::ACTIVE
        );
        $this->addReference(self::CHILD, $child);
        $manager->persist($child);

        $invitee = $this->buildUser(
            email: 'invitee_demo@example.com',
            username: 'invitee_demo',
            firstName: 'Invitee',
            lastName: 'Demo',
            systemRole: SystemRole::CUSTOMER,
            familyRole: FamilyRole::SOLO,
            status: UserStatus::ACTIVE
        );
        $this->addReference(self::INVITEE, $invitee);
        $manager->persist($invitee);

        $suspended = $this->buildUser(
            email: 'suspended_demo@example.com',
            username: 'suspended_demo',
            firstName: 'Suspended',
            lastName: 'User',
            systemRole: SystemRole::CUSTOMER,
            familyRole: FamilyRole::SOLO,
            status: UserStatus::SUSPENDED
        );
        $this->addReference(self::SUSPENDED, $suspended);
        $manager->persist($suspended);

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function buildUser(
        string $email,
        string $username,
        string $firstName,
        string $lastName,
        SystemRole $systemRole,
        FamilyRole $familyRole,
        UserStatus $status,
        array $roles = ['ROLE_USER'],
    ): User {
        $user = (new User())
            ->setEmail($email)
            ->setUsername($username)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setBirthDate(new DateTime('1995-01-01'))
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setRoles($roles)
            ->setStatus($status)
            ->setSystemRole($systemRole)
            ->setFamilyRole($familyRole)
            ->setPreferences([
                'notifications' => [
                    'matrix' => [
                        'account_activity' => ['email' => true, 'browser' => true, 'app' => false],
                    ],
                ],
            ])
            ->setEmailVerifiedAt(new DateTimeImmutable())
            ->setCreatedAt(new DateTimeImmutable())
            ->setUpdatedAt(new DateTimeImmutable());

        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));

        return $user;
    }
}
