<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AccountNotification;
use App\Entity\AuditTrail;
use App\Entity\Badge;
use App\Entity\Family;
use App\Entity\FamilyBadge;
use App\Entity\FamilyInvitation;
use App\Entity\FamilyMembership;
use App\Entity\User;
use App\Enum\BadgeScope;
use App\Enum\FamilyRole;
use App\Enum\InvitationStatus;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use App\Tests\DatabaseTestCase;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class AdminUmsCrudTest extends DatabaseTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testUserCrud(): void
    {
        $admin = $this->createAdmin(true);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/portal/admin/users');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', '/portal/admin/users/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['user_admin[email]'] = 'crud_user@example.com';
        $form['user_admin[username]'] = 'crud_user';
        $form['user_admin[firstName]'] = 'Crud';
        $form['user_admin[lastName]'] = 'User';
        $form['user_admin[systemRole]'] = SystemRole::CUSTOMER->value;
        $form['user_admin[status]'] = UserStatus::ACTIVE->value;
        $form['user_admin[familyRole]'] = FamilyRole::SOLO->value;
        $form['user_admin[birthDate]'] = '2000-01-01';
        $form['user_admin[locale]'] = 'en';
        $form['user_admin[timeZone]'] = 'UTC';
        $form['user_admin[password]'] = 'password123';
        $this->client->submit($form);

        self::assertResponseRedirects();

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'crud_user@example.com']);
        self::assertNotNull($user);

        $crawler = $this->client->request('GET', '/portal/admin/users/' . $user->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['user_admin[firstName]'] = 'Updated';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $deletePage = $this->client->request('GET', '/portal/admin/users/' . $user->getId());
        $token = $deletePage->filter(sprintf('form[action="/portal/admin/users/%s/delete"] input[name="_token"]', $user->getId()))->attr('value');
        $this->client->request('POST', '/portal/admin/users/' . $user->getId() . '/delete', ['_token' => $token]);
        self::assertResponseRedirects('/portal/admin/users');
    }

    public function testFamilyCrud(): void
    {
        $admin = $this->createAdmin(true);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/ums/families/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['family_admin[name]'] = 'Crud Household';
        $form['family_admin[joinCode]'] = 'CRUDFAM';
        $form['family_admin[codeExpiresAt]'] = '2026-02-09 10:00';
        $form['family_admin[createdBy]'] = $admin->getId();
        $form['family_admin[createdAt]'] = '2026-02-09 09:00';
        $form['family_admin[updatedAt]'] = '2026-02-09 09:30';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/families');

        $family = $this->entityManager->getRepository(Family::class)->findOneBy(['name' => 'Crud Household']);
        self::assertNotNull($family);

        $crawler = $this->client->request('GET', '/portal/admin/ums/families/' . $family->getId() . '/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['family_admin[name]'] = 'Crud Household Updated';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/families');

        $token = $this->extractTokenFromList('/portal/admin/ums/families', '/portal/admin/ums/families/' . $family->getId() . '/delete');
        $this->client->request('POST', '/portal/admin/ums/families/' . $family->getId() . '/delete', ['_token' => $token]);
        self::assertResponseRedirects('/portal/admin/ums/families');
    }

    public function testInvitationCrud(): void
    {
        $admin = $this->createAdmin(true);
        $family = $this->createFamily($admin);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/ums/invitations/new');
        $form = $crawler->selectButton('Save')->form();
        $form['family_invitation_admin[invitedEmail]'] = 'invitee@example.com';
        $form['family_invitation_admin[joinCode]'] = 'INV123';
        $form['family_invitation_admin[status]'] = InvitationStatus::PENDING->value;
        $form['family_invitation_admin[expiresAt]'] = '2026-02-09 12:00';
        $form['family_invitation_admin[family]'] = $family->getId();
        $form['family_invitation_admin[createdBy]'] = $admin->getId();
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/invitations');

        $invitation = $this->entityManager->getRepository(FamilyInvitation::class)->findOneBy(['joinCode' => 'INV123']);
        self::assertNotNull($invitation);

        $crawler = $this->client->request('GET', '/portal/admin/ums/invitations/' . $invitation->getId() . '/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['family_invitation_admin[status]'] = InvitationStatus::ACCEPTED->value;
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/invitations');

        $token = $this->extractTokenFromList('/portal/admin/ums/invitations', '/portal/admin/ums/invitations/' . $invitation->getId() . '/delete');
        $this->client->request('POST', '/portal/admin/ums/invitations/' . $invitation->getId() . '/delete', ['_token' => $token]);
        self::assertResponseRedirects('/portal/admin/ums/invitations');
    }

    public function testMembershipCrud(): void
    {
        $admin = $this->createAdmin(true);
        $family = $this->createFamily($admin);
        $member = $this->createUser('member@example.com', 'member_user');
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/ums/memberships/new');
        $form = $crawler->selectButton('Save')->form();
        $form['family_membership_create[family]'] = $family->getId();
        $form['family_membership_create[user]'] = $member->getId();
        $form['family_membership_create[role]'] = FamilyRole::CHILD->value;
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/memberships');

        $membership = $this->entityManager->getRepository(FamilyMembership::class)->findOneBy(['user' => $member]);
        self::assertNotNull($membership);

        $crawler = $this->client->request('GET', '/portal/admin/ums/memberships/' . $membership->getId() . '/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['family_membership_admin[role]'] = FamilyRole::PARENT->value;
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/memberships');

        $token = $this->extractTokenFromList('/portal/admin/ums/memberships', '/portal/admin/ums/memberships/' . $membership->getId() . '/delete');
        $this->client->request('POST', '/portal/admin/ums/memberships/' . $membership->getId() . '/delete', ['_token' => $token]);
        self::assertResponseRedirects('/portal/admin/ums/memberships');
    }

    public function testBadgeCrud(): void
    {
        $admin = $this->createAdmin(true);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/ums/badges/new');
        $form = $crawler->selectButton('Save')->form();
        $form['badge_admin[name]'] = 'Welcome';
        $form['badge_admin[code]'] = 'WELCOME';
        $form['badge_admin[scope]'] = BadgeScope::USER->value;
        $form['badge_admin[description]'] = 'Welcome badge';
        $form['badge_admin[icon]'] = 'bx-star';
        $form['badge_admin[requiredPoints]'] = '10';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/badges');

        $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['code' => 'WELCOME']);
        self::assertNotNull($badge);

        $crawler = $this->client->request('GET', '/portal/admin/ums/badges/' . $badge->getId() . '/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['badge_admin[name]'] = 'Welcome Updated';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/badges');

        $token = $this->extractTokenFromList('/portal/admin/ums/badges', '/portal/admin/ums/badges/' . $badge->getId() . '/delete');
        $this->client->request('POST', '/portal/admin/ums/badges/' . $badge->getId() . '/delete', ['_token' => $token]);
        self::assertResponseRedirects('/portal/admin/ums/badges');
    }

    public function testFamilyBadgeCrud(): void
    {
        $admin = $this->createAdmin(true);
        $family = $this->createFamily($admin);
        $badge = $this->createBadge();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/ums/family-badges/new');
        $form = $crawler->selectButton('Save')->form();
        $form['family_badge_admin[family]'] = $family->getId();
        $form['family_badge_admin[badge]'] = $badge->getId();
        $form['family_badge_admin[awardedAt]'] = '2026-02-09 10:00';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/family-badges');

        $record = $this->entityManager->getRepository(FamilyBadge::class)->findOneBy(['family' => $family, 'badge' => $badge]);
        self::assertNotNull($record);

        $crawler = $this->client->request('GET', '/portal/admin/ums/family-badges/' . $record->getId() . '/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['family_badge_admin[awardedAt]'] = '2026-02-10 10:00';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/family-badges');

        $token = $this->extractTokenFromList('/portal/admin/ums/family-badges', '/portal/admin/ums/family-badges/' . $record->getId() . '/delete');
        $this->client->request('POST', '/portal/admin/ums/family-badges/' . $record->getId() . '/delete', ['_token' => $token]);
        self::assertResponseRedirects('/portal/admin/ums/family-badges');
    }

    public function testAccountNotificationCrud(): void
    {
        $admin = $this->createAdmin(true);
        $user = $this->createUser('notify@example.com', 'notify_user');
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/ums/notifications/new');
        $form = $crawler->selectButton('Save')->form();
        $form['account_notification_admin[user]'] = $user->getId();
        $form['account_notification_admin[key]'] = 'welcome';
        $form['account_notification_admin[channel]'] = 'email';
        $form['account_notification_admin[status]'] = 'PENDING';
        $form['account_notification_admin[createdAt]'] = '2026-02-09 10:00';
        $form['account_notification_admin[sentAt]'] = '2026-02-09 10:30';
        $form['account_notification_admin[lastError]'] = '';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/notifications');

        $record = $this->entityManager->getRepository(AccountNotification::class)->findOneBy(['key' => 'welcome']);
        self::assertNotNull($record);

        $crawler = $this->client->request('GET', '/portal/admin/ums/notifications/' . $record->getId() . '/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['account_notification_admin[status]'] = 'SENT';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/notifications');

        $token = $this->extractTokenFromList('/portal/admin/ums/notifications', '/portal/admin/ums/notifications/' . $record->getId() . '/delete');
        $this->client->request('POST', '/portal/admin/ums/notifications/' . $record->getId() . '/delete', ['_token' => $token]);
        self::assertResponseRedirects('/portal/admin/ums/notifications');
    }

    public function testAuditTrailCrud(): void
    {
        $admin = $this->createAdmin(true);
        $user = $this->createUser('audit@example.com', 'audit_user');
        $family = $this->createFamily($admin);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/ums/audit-trails/new');
        $form = $crawler->selectButton('Save')->form();
        $form['audit_trail_admin[action]'] = 'user.login';
        $form['audit_trail_admin[user]'] = $user->getId();
        $form['audit_trail_admin[family]'] = $family->getId();
        $form['audit_trail_admin[channel]'] = 'web';
        $form['audit_trail_admin[ipAddress]'] = '127.0.0.1';
        $form['audit_trail_admin[userAgent]'] = 'PHPUnit';
        $form['audit_trail_admin[createdAt]'] = '2026-02-09 10:00';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/audit-trails');

        $record = $this->entityManager->getRepository(AuditTrail::class)->findOneBy(['action' => 'user.login']);
        self::assertNotNull($record);

        $crawler = $this->client->request('GET', '/portal/admin/ums/audit-trails/' . $record->getId() . '/edit');
        $form = $crawler->selectButton('Save')->form();
        $form['audit_trail_admin[action]'] = 'user.login.updated';
        $this->client->submit($form);
        self::assertResponseRedirects('/portal/admin/ums/audit-trails');

        $token = $this->extractTokenFromList('/portal/admin/ums/audit-trails', '/portal/admin/ums/audit-trails/' . $record->getId() . '/delete');
        $this->client->request('POST', '/portal/admin/ums/audit-trails/' . $record->getId() . '/delete', ['_token' => $token]);
        self::assertResponseRedirects('/portal/admin/ums/audit-trails');
    }

    private function extractTokenFromList(string $listPath, string $actionPath): string
    {
        $crawler = $this->client->request('GET', $listPath);
        $selector = sprintf('form[action="%s"] input[name="_token"]', $actionPath);
        $token = $crawler->filter($selector)->attr('value');
        self::assertNotNull($token);

        return $token;
    }

    private function createAdmin(bool $persist): User
    {
        $admin = new User();
        $admin
            ->setEmail('admin_' . uniqid('', false) . '@example.com')
            ->setUsername('admin_' . uniqid('', false))
            ->setPassword('password')
            ->setFirstName('Admin')
            ->setLastName('User')
            ->setBirthDate(new DateTime('1990-01-01'))
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setRoles(['ROLE_ADMIN'])
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole(SystemRole::ADMIN)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setEmailVerifiedAt(new DateTimeImmutable());

        if ($persist) {
            $this->entityManager->persist($admin);
            $this->entityManager->flush();
        }

        return $admin;
    }

    private function createUser(string $email, string $username): User
    {
        $user = new User();
        $user
            ->setEmail($email)
            ->setUsername($username)
            ->setPassword('password')
            ->setFirstName('Demo')
            ->setLastName('User')
            ->setBirthDate(new DateTime('1995-01-01'))
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setRoles(['ROLE_USER'])
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole(SystemRole::CUSTOMER)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setEmailVerifiedAt(new DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createFamily(User $creator): Family
    {
        $family = new Family();
        $family
            ->setName('Family ' . uniqid('', false))
            ->setCreatedAt(new DateTimeImmutable())
            ->setCreatedBy($creator);

        $this->entityManager->persist($family);
        $this->entityManager->flush();

        return $family;
    }

    private function createBadge(): Badge
    {
        $badge = new Badge();
        $badge
            ->setName('Badge ' . uniqid('', false))
            ->setCode('CODE_' . strtoupper(substr(uniqid('', false), -6)))
            ->setScope(BadgeScope::USER)
            ->setRequiredPoints(5);

        $this->entityManager->persist($badge);
        $this->entityManager->flush();

        return $badge;
    }
}
