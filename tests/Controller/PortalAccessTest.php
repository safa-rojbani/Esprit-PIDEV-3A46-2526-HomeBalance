<?php

namespace App\Tests\Controller;

use App\Tests\Fixtures\PortalRouteFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PortalAccessTest extends WebTestCase
{
    #[DataProvider('publicAuthPagesProvider')]
    public function testPublicAuthPagesAccessible(string $path, string $selector, ?string $contains): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists($selector);

        if ($contains !== null) {
            $this->assertSelectorTextContains($selector, $contains);
        }
    }

    #[DataProvider('protectedRoutesProvider')]
    public function testProtectedRoutesRequireAuthentication(string $path, string $redirect): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $this->assertResponseRedirects($redirect);
    }

    public static function publicAuthPagesProvider(): array
    {
        return PortalRouteFixtures::publicAuthPages();
    }

    public static function protectedRoutesProvider(): array
    {
        return PortalRouteFixtures::protectedRoutes();
    }
}
