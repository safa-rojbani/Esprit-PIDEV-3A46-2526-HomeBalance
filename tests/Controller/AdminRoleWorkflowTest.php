<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\RoleChangeRequest;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use App\Tests\DatabaseTestCase;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class AdminRoleWorkflowTest extends DatabaseTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAdminCanRequestAndApproveRoleChange(): void
    {
        $admin = $this->createUser(
            'admin_role@example.com',
            'admin_role',
            SystemRole::ADMIN,
            ['ROLE_ADMIN']
        );
        $target = $this->createUser('target_role@example.com', 'target_role', SystemRole::CUSTOMER);

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/users/' . $target->getId());
        self::assertResponseIsSuccessful();

        $requestToken = $crawler
            ->filter(sprintf('form[action="/portal/admin/users/%s/role-change-request"] input[name="role_change_request[_token]"]', $target->getId()))
            ->attr('value');
        self::assertNotNull($requestToken);

        $this->client->request('POST', '/portal/admin/users/' . $target->getId() . '/role-change-request', [
            'role_change_request' => [
                '_token' => $requestToken,
                'requestedRole' => SystemRole::ADMIN->value,
            ],
        ]);
        self::assertResponseRedirects('/portal/admin/users/' . $target->getId());

        $request = $this->entityManager->getRepository(RoleChangeRequest::class)->findOneBy([
            'user' => $target,
            'status' => RoleChangeRequest::STATUS_PENDING,
        ]);
        self::assertInstanceOf(RoleChangeRequest::class, $request);

        $crawler = $this->client->request('GET', '/portal/admin/users/' . $target->getId());
        self::assertResponseIsSuccessful();
        $approveToken = $crawler
            ->filter(sprintf('form[action="/portal/admin/users/%s/role-change-requests/%d/approve"] input[name="_token"]', $target->getId(), $request->getId()))
            ->attr('value');
        self::assertNotNull($approveToken);

        $this->client->request('POST', sprintf('/portal/admin/users/%s/role-change-requests/%d/approve', $target->getId(), $request->getId()), [
            '_token' => $approveToken,
        ]);
        self::assertResponseRedirects('/portal/admin/users/' . $target->getId());

        $this->entityManager->clear();
        $updatedTarget = $this->entityManager->getRepository(User::class)->find($target->getId());
        self::assertInstanceOf(User::class, $updatedTarget);
        self::assertSame(SystemRole::ADMIN, $updatedTarget->getSystemRole());
        self::assertContains('ROLE_ADMIN', $updatedTarget->getRoles());
    }

    public function testAuditExportReturnsCsv(): void
    {
        $admin = $this->createUser(
            'admin_export@example.com',
            'admin_export',
            SystemRole::ADMIN,
            ['ROLE_ADMIN']
        );
        $target = $this->createUser('target_export@example.com', 'target_export', SystemRole::CUSTOMER);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/users/' . $target->getId() . '/audit-export');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, string $username, SystemRole $systemRole, array $roles = ['ROLE_USER']): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setUsername($username)
            ->setPassword('password')
            ->setFirstName('Demo')
            ->setLastName('User')
            ->setBirthDate(new DateTime('1994-01-01'))
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setRoles($roles)
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole($systemRole)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setEmailVerifiedAt(new DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
