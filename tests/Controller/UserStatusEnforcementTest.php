<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use App\Tests\DatabaseTestCase;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class UserStatusEnforcementTest extends DatabaseTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testSuspendedUserIsLoggedOutOnNextRequest(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $user->setStatus(UserStatus::SUSPENDED);
        $this->entityManager->flush();

        $this->client->request('GET', '/portal/account');
        self::assertResponseRedirects('/portal/auth/login');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'temporarily suspended');
    }

    public function testDeletedUserIsLoggedOutOnNextRequest(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $user->setStatus(UserStatus::DELETED);
        $this->entityManager->flush();

        $this->client->request('GET', '/portal/account');
        self::assertResponseRedirects('/portal/auth/login');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'account has been deleted');
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail(sprintf('user_%s@example.com', uniqid('', false)));
        $user->setUsername(sprintf('user_%s', uniqid('', false)));
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setBirthDate(new DateTime('2000-01-01'));
        $user->setLocale('en');
        $user->setTimeZone('UTC');
        $user->setRoles(['ROLE_USER']);
        $user->setStatus(UserStatus::ACTIVE);
        $user->setSystemRole(SystemRole::CUSTOMER);
        $user->setFamilyRole(FamilyRole::SOLO);
        $user->setPreferences([]);
        $user->setEmailVerifiedAt(new DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
