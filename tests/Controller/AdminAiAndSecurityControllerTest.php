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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminAiAndSecurityControllerTest extends DatabaseTestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testAiPlanDryRunRequiresStepUpBeforeExecution(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/users');
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/portal/admin/ai/plan', [
            'prompt' => 'search users with risk filters from last 30 days',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $planPayload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($planPayload);
        self::assertTrue((bool) ($planPayload['ok'] ?? false));
        self::assertSame('search_users_with_risk_filters', $planPayload['intent']['intent'] ?? null);

        $sessionId = (string) ($planPayload['session_id'] ?? '');
        self::assertNotSame('', $sessionId);

        $this->client->jsonRequest('POST', '/portal/admin/ai/dry-run', [
            'session_id' => $sessionId,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $dryPayload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($dryPayload);
        self::assertTrue((bool) ($dryPayload['ok'] ?? false));
        $confirmToken = (string) ($dryPayload['confirm_token'] ?? '');
        self::assertNotSame('', $confirmToken);

        $this->client->jsonRequest('POST', '/portal/admin/ai/execute', [
            'session_id' => $sessionId,
            'confirm_token' => $confirmToken,
        ]);
        self::assertResponseStatusCodeSame(423);
        $blockedPayload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($blockedPayload);
        self::assertTrue((bool) ($blockedPayload['step_up_required'] ?? false));

        $crawler = $this->client->request('GET', '/portal/admin/console/security-ai?step=step-up&session_id=' . $sessionId);
        self::assertResponseIsSuccessful();
        $manualToken = (string) $crawler->filter('form[action="/portal/admin/ums/security/manual-fallback"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/portal/admin/ums/security/manual-fallback', [
            '_token' => $manualToken,
            'action_key' => 'admin.ai.execute',
            'current_password' => 'password123',
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->client->jsonRequest('POST', '/portal/admin/ai/execute', [
            'session_id' => $sessionId,
            'confirm_token' => $confirmToken,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $executePayload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($executePayload);
        self::assertTrue((bool) ($executePayload['ok'] ?? false));
        self::assertSame('SUCCESS', $executePayload['result']['result'] ?? null);
    }

    public function testAiDangerousIntentIsBlockedWithoutStepUp(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/users');
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/portal/admin/ai/plan', [
            'prompt' => 'suspend users with frequent password changes',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $planPayload = json_decode((string) $this->client->getResponse()->getContent(), true);
        $sessionId = (string) ($planPayload['session_id'] ?? '');
        self::assertNotSame('', $sessionId);

        $this->client->jsonRequest('POST', '/portal/admin/ai/dry-run', [
            'session_id' => $sessionId,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $dryPayload = json_decode((string) $this->client->getResponse()->getContent(), true);
        $confirmToken = (string) ($dryPayload['confirm_token'] ?? '');

        $this->client->jsonRequest('POST', '/portal/admin/ai/execute', [
            'session_id' => $sessionId,
            'confirm_token' => $confirmToken,
        ]);
        self::assertResponseStatusCodeSame(423);
        $executePayload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($executePayload);
        self::assertTrue((bool) ($executePayload['step_up_required'] ?? false));
    }

    public function testAiAmbiguousPromptReturnsRefinementMessage(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/users');
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/portal/admin/ai/plan', [
            'prompt' => 'Do something with all users',
        ]);

        self::assertResponseStatusCodeSame(422);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertFalse((bool) ($payload['ok'] ?? true));
        self::assertNotEmpty((string) ($payload['user_message'] ?? ''));
    }

    public function testSecurityEndpointsValidationAndManualFallback(): void
    {
        $admin = $this->createAdmin();
        $target = $this->createUser('target_demo@example.com', 'target_demo');
        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/users');
        self::assertResponseIsSuccessful();
        $crawler = $this->client->request('GET', '/portal/admin/console/security-ai?step=step-up');
        self::assertResponseIsSuccessful();

        $enrollToken = (string) $crawler->filter('form[action="/portal/admin/ums/security/face/enroll"] input[name="_token"]')->attr('value');
        $verifyToken = (string) $crawler->filter('form[action="/portal/admin/ums/security/face/verify"] input[name="_token"]')->attr('value');
        $manualToken = (string) $crawler->filter('form[action="/portal/admin/ums/security/manual-fallback"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/portal/admin/ums/security/face/enroll', [
            '_token' => $enrollToken,
            'consent' => '1',
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/portal/admin/ums/security/face/verify', [
            '_token' => $verifyToken,
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/portal/admin/ums/security/manual-fallback', [
            '_token' => $manualToken,
            'action_key' => 'admin.user.reset_password',
            'target_user_id' => $target->getId(),
            'current_password' => 'wrong-password',
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/portal/admin/ums/security/manual-fallback', [
            '_token' => $manualToken,
            'action_key' => 'admin.user.reset_password',
            'target_user_id' => $target->getId(),
            'current_password' => 'password123',
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->client->request('POST', '/portal/admin/ums/security/face/verify', [
            '_token' => $verifyToken,
            'action_key' => 'admin.user.reset_password',
            'target_user_id' => $target->getId(),
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $verifyPayload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($verifyPayload);
        self::assertTrue((bool) ($verifyPayload['ok'] ?? false));
        self::assertSame('already_verified', $verifyPayload['status'] ?? null);
    }

    private function createAdmin(): User
    {
        $user = new User();
        $user
            ->setEmail('admin_ai_' . uniqid('', false) . '@example.com')
            ->setUsername('admin_ai_' . uniqid('', false))
            ->setPassword('')
            ->setFirstName('Admin')
            ->setLastName('AI')
            ->setBirthDate(new DateTime('1990-01-01'))
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setRoles(['ROLE_ADMIN'])
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole(SystemRole::ADMIN)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setEmailVerifiedAt(new DateTimeImmutable())
            ->setCreatedAt(new DateTimeImmutable())
            ->setUpdatedAt(new DateTimeImmutable());

        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createUser(string $email, string $username): User
    {
        $user = new User();
        $user
            ->setEmail($email)
            ->setUsername($username)
            ->setPassword('')
            ->setFirstName('Target')
            ->setLastName('User')
            ->setBirthDate(new DateTime('2000-01-01'))
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setRoles(['ROLE_USER'])
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole(SystemRole::CUSTOMER)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setEmailVerifiedAt(new DateTimeImmutable())
            ->setCreatedAt(new DateTimeImmutable())
            ->setUpdatedAt(new DateTimeImmutable());

        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
