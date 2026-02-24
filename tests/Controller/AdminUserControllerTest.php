<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Family;
use App\Entity\FamilyMembership;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use App\Repository\FamilyMembershipRepository;
use App\Tests\DatabaseTestCase;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class AdminUserControllerTest extends DatabaseTestCase
{
    private EntityManagerInterface $entityManager;
    private FamilyMembershipRepository $membershipRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->membershipRepository = $container->get(FamilyMembershipRepository::class);
    }

    public function testAdminCanDetachFamily(): void
    {
        [$admin, $user] = $this->createAdminWithUserFamily();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/users/' . $user->getId());
        self::assertResponseIsSuccessful();

        $tokenSelector = sprintf('form[action="/portal/admin/users/%s/detach-family"] input[name="_token"]', $user->getId());
        $token = $crawler->filter($tokenSelector)->attr('value');
        self::assertNotNull($token);

        $this->client->request('POST', '/portal/admin/users/' . $user->getId() . '/detach-family', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/portal/admin/users/' . $user->getId());

        $refetched = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($refetched);
        self::assertNull($refetched->getFamily());
        self::assertSame(FamilyRole::SOLO, $refetched->getFamilyRole());

        $activeMembership = $this->membershipRepository->findActiveMembershipForUser($refetched);
        self::assertNull($activeMembership);
    }

    public function testAdminCanReinviteFormerFamily(): void
    {
        [$admin, $user, $family] = $this->createAdminWithUserFamily();
        $this->detachUserFromFamily($user);

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/users/' . $user->getId());
        self::assertResponseIsSuccessful();

        $tokenSelector = sprintf('form[action="/portal/admin/users/%s/reinvite-family"] input[name="_token"]', $user->getId());
        $token = $crawler->filter($tokenSelector)->attr('value');
        self::assertNotNull($token);

        self::assertNull($family->getUpdatedAt());

        $this->client->request('POST', '/portal/admin/users/' . $user->getId() . '/reinvite-family', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/portal/admin/users/' . $user->getId());

        $refetchedFamily = $this->entityManager->getRepository(Family::class)->find($family->getId());
        self::assertNotNull($refetchedFamily);
        self::assertNotNull($refetchedFamily->getUpdatedAt());
        self::assertNotEmpty($refetchedFamily->getJoinCode());

        $refetchedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($refetchedUser);
        self::assertNull($refetchedUser->getFamily());
    }

    public function testAdminUsersIndexSupportsSortAndFilterQueryWithoutPaginatorException(): void
    {
        $admin = $this->createAdmin();
        $user = new User();
        $user
            ->setEmail('query_member_' . uniqid('', false) . '@example.com')
            ->setUsername('query_member_' . uniqid('', false))
            ->setPassword('password')
            ->setFirstName('Query')
            ->setLastName('Member')
            ->setBirthDate(new DateTime('2001-01-01'))
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setRoles(['ROLE_USER'])
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole(SystemRole::CUSTOMER)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setEmailVerifiedAt(new DateTimeImmutable());

        $this->entityManager->persist($admin);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($admin);

        $this->client->request('GET', '/portal/admin/users?q=query_member&sort=createdAt&dir=DESC');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h4', 'Tous les utilisateurs');
    }

    /**
     * @return array{0: User, 1: User, 2: Family}
     */
    private function createAdminWithUserFamily(): array
    {
        $admin = $this->createAdmin();

        $user = new User();
        $user
            ->setEmail('member_' . uniqid('', false) . '@example.com')
            ->setUsername('member_' . uniqid('', false))
            ->setPassword('password')
            ->setFirstName('Family')
            ->setLastName('Member')
            ->setBirthDate(new DateTime('2001-01-01'))
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setRoles(['ROLE_USER'])
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole(SystemRole::CUSTOMER)
            ->setFamilyRole(FamilyRole::CHILD)
            ->setEmailVerifiedAt(new DateTimeImmutable());

        $family = new Family();
        $family
            ->setName('Household ' . substr($user->getUsername(), -4))
            ->setCreatedAt(new DateTimeImmutable())
            ->setCreatedBy($user);

        $membership = new FamilyMembership($family, $user, FamilyRole::CHILD);
        $family->addMembership($membership);
        $user->setFamily($family);

        $this->entityManager->persist($admin);
        $this->entityManager->persist($user);
        $this->entityManager->persist($family);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return [$admin, $user, $family];
    }

    private function detachUserFromFamily(User $user): void
    {
        $membership = $this->membershipRepository->findActiveMembershipForUser($user);
        if ($membership !== null) {
            $membership->leave();
        }

        $user
            ->setFamily(null)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();
    }

    private function createAdmin(): User
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

        return $admin;
    }
}
