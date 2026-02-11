<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use App\Tests\Fixtures\ForgotPasswordFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ForgotPasswordTest extends WebTestCase
{
    #[DataProvider('invalidEmailProvider')]
    public function testSubmittingInvalidEmailShowsError(string $email): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/portal/auth/forgot-password');

        $form = $crawler->selectButton('Send Reset Link')->form([
            'email' => $email,
        ]);

        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-danger', 'Please enter a valid email address.');
    }

    #[DataProvider('validEmailProvider')]
    public function testSubmittingValidEmailShowsSuccessFlash(string $email): void
    {
        $client = static::createClient();
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);
        static::getContainer()->set(UserRepository::class, $userRepository);
        $crawler = $client->request('GET', '/portal/auth/forgot-password');

        $form = $crawler->selectButton('Send Reset Link')->form([
            'email' => $email,
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/portal/auth/forgot-password');

        $client->followRedirect();

        $this->assertSelectorTextContains('.alert-success', 'If the email exists, we just sent password reset instructions.');
    }

    public static function invalidEmailProvider(): array
    {
        return ForgotPasswordFixtures::invalidEmails();
    }

    public static function validEmailProvider(): array
    {
        return ForgotPasswordFixtures::validEmails();
    }
}
