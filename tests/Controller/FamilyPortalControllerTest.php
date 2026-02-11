<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use App\Service\FamilyManager;
use App\Tests\DatabaseTestCase;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class FamilyPortalControllerTest extends DatabaseTestCase
{
    private EntityManagerInterface $entityManager;
    private FamilyManager $familyManager;

    protected function setUp(): void
    {
        parent::setUp();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->familyManager = $container->get(FamilyManager::class);
    }

    public function testSettingsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/portal/account/family');

        self::assertResponseRedirects('/portal/auth/login');
    }

    public function testCreateFamilyPersistsAndAttachesUser(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/portal/onboarding/family');
        $token = $crawler->filter('form[action="/portal/onboarding/family/create"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/portal/onboarding/family/create', [
            '_token' => $token,
            'familyName' => 'The Parkers',
        ]);

        self::assertResponseRedirects(null, Response::HTTP_FOUND);

        $reloadedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloadedUser);
        self::assertNotNull($reloadedUser->getFamily());
        self::assertSame('The Parkers', $reloadedUser->getFamily()?->getName());
        self::assertSame(FamilyRole::PARENT, $reloadedUser->getFamilyRole());
    }

    public function testJoinFamilyWithValidCodeAttachesUser(): void
    {
        $parent = $this->createUser([
            'email' => 'parent@example.com',
            'username' => 'parent_' . uniqid('', false),
        ]);
        $family = $this->familyManager->createFamily($parent, 'Ramos Household');
        $joinCode = $family->getJoinCode();
        self::assertNotNull($joinCode);

        $joiner = $this->createUser([
            'email' => 'child@example.com',
            'username' => 'child_' . uniqid('', false),
        ]);
        $this->client->loginUser($joiner);
        $crawler = $this->client->request('GET', '/portal/onboarding/family');
        $token = $crawler->filter('form[action="/portal/onboarding/family/join"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/portal/onboarding/family/join', [
            '_token' => $token,
            'joinCode' => $joinCode,
        ]);

        self::assertResponseRedirects(null, Response::HTTP_FOUND);
        $reloadedJoiner = $this->entityManager->getRepository(User::class)->find($joiner->getId());
        self::assertNotNull($reloadedJoiner);
        self::assertSame($family->getId(), $reloadedJoiner->getFamily()?->getId());
        self::assertSame(FamilyRole::CHILD, $reloadedJoiner->getFamilyRole());
    }

    private function createUser(array $overrides = []): User
    {
        $user = new User();
        $user->setEmail($overrides['email'] ?? sprintf('user_%s@example.com', uniqid('', false)));
        $user->setUsername($overrides['username'] ?? sprintf('user_%s', uniqid('', false)));
        $user->setPassword($overrides['password'] ?? 'password');
        $user->setFirstName($overrides['firstName'] ?? 'Test');
        $user->setLastName($overrides['lastName'] ?? 'User');
        $user->setBirthDate($overrides['birthDate'] ?? new DateTime('2000-01-01'));
        $user->setLocale($overrides['locale'] ?? 'en');
        $user->setTimeZone($overrides['timeZone'] ?? 'UTC');
        $user->setRoles($overrides['roles'] ?? ['ROLE_USER']);
        $user->setStatus($overrides['status'] ?? UserStatus::ACTIVE);
        $user->setSystemRole($overrides['systemRole'] ?? SystemRole::CUSTOMER);
        $user->setFamilyRole($overrides['familyRole'] ?? FamilyRole::SOLO);
        $user->setPreferences($overrides['preferences'] ?? []);
        $user->setEmailVerifiedAt(new DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
