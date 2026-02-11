<?php

namespace App\Tests\Controller;

use App\Entity\Family;
use App\Entity\FamilyMembership;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use App\Tests\DatabaseTestCase;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountSettingsTest extends DatabaseTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    public function testPasswordChangeFailsWithInvalidCurrentPassword(): void
    {
        $user = $this->createVerifiedUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/portal/account', [
            '_token' => $this->accountPasswordToken(),
            'section' => 'password',
            'currentPassword' => 'wrong-password',
            'newPassword' => 'NewPassword123!',
            'confirmPassword' => 'NewPassword123!',
        ]);

        self::assertResponseRedirects('/portal/account');
        $this->client->followRedirect();

        $refetched = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($refetched);
        self::assertSelectorExists('.alert-danger');
    }

    public function testPasswordChangeSucceedsWithValidCredentials(): void
    {
        $user = $this->createVerifiedUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/portal/account', [
            '_token' => $this->accountPasswordToken(),
            'section' => 'password',
            'currentPassword' => 'password',
            'newPassword' => 'BetterPassword#2026',
            'confirmPassword' => 'BetterPassword#2026',
        ]);

        self::assertResponseRedirects('/portal/account');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');

        $refetched = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($refetched);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($refetched, 'BetterPassword#2026'));
    }

    public function testPreferencesValidationSurfaceErrors(): void
    {
        $user = $this->createVerifiedUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/portal/account/preferences', [
            '_token' => $this->preferencesToken(),
            'section' => 'preferences',
            'preferences' => [
                'topics' => [],
                'channels' => [],
                'quietStart' => '20:30',
                'quietEnd' => '',
            ],
        ]);

        if ($this->client->getResponse()->isRedirection()) {
            $this->client->followRedirect();
        }

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-danger li');
    }

    public function testPreferencesSavePersistsCommunicationSettings(): void
    {
        $user = $this->createVerifiedUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/portal/account/preferences', [
            '_token' => $this->preferencesToken(),
            'section' => 'preferences',
            'preferences' => [
                'topics' => ['budget', 'tasks'],
                'channels' => ['email', 'push'],
                'quietStart' => '22:00',
                'quietEnd' => '07:00',
                'weeklySummary' => '1',
            ],
        ]);

        self::assertResponseRedirects('/portal/account/preferences');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-success');

        $refetched = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($refetched);

        $preferences = $refetched->getPreferences();
        self::assertEquals(['budget', 'tasks'], $preferences['communication']['topics']);
        self::assertEquals(['email', 'push'], $preferences['communication']['channels']);
        self::assertEquals(['start' => '22:00', 'end' => '07:00'], $preferences['communication']['quietHours']);
        self::assertTrue($preferences['communication']['weeklySummary']);
    }

    private function createVerifiedUser(): User
    {
        $user = new User();
        $user
            ->setEmail('account_' . uniqid('', false) . '@example.com')
            ->setUsername('account_' . uniqid('', false))
            ->setFirstName('Test')
            ->setLastName('User')
            ->setBirthDate(new DateTime('2000-01-01'))
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole(SystemRole::CUSTOMER)
            ->setFamilyRole(FamilyRole::PARENT)
            ->setEmailVerifiedAt(new DateTimeImmutable());

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $user->setPreferences([
            'profile' => [],
            'communication' => [
                'topics' => ['tasks'],
                'channels' => ['email'],
                'quietHours' => ['start' => null, 'end' => null],
                'weeklySummary' => false,
            ],
        ]);

        $family = new Family();
        $now = new DateTimeImmutable();
        $family
            ->setName('Household ' . substr($user->getUsername(), -5))
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setCreatedBy($user);

        $membership = new FamilyMembership($family, $user, FamilyRole::PARENT);
        $family->addMembership($membership);
        $user->setFamily($family);

        $this->entityManager->persist($user);
        $this->entityManager->persist($family);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return $user;
    }

    private function accountPasswordToken(): string
    {
        return $this->extractSectionToken('/portal/account', 'password');
    }

    private function preferencesToken(): string
    {
        return $this->extractSectionToken('/portal/account/preferences', 'preferences');
    }

    private function extractSectionToken(string $path, string $section): string
    {
        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->reduce(function (Crawler $candidate) use ($section) {
            $match = $candidate->filter(sprintf('input[name="section"][value="%s"]', $section));

            return $match->count() > 0;
        })->first();

        self::assertGreaterThan(0, $form->count(), sprintf('Form for section "%s" not found on %s', $section, $path));

        $token = $form->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token, sprintf('Missing CSRF token for section "%s" on %s', $section, $path));

        return $token;
    }

}
