<?php

declare(strict_types=1);

namespace App\Tests\Service\Ai;

use App\Service\Ai\AdminIntentLlmService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AdminIntentLlmServiceTest extends TestCase
{
    #[DataProvider('promptProvider')]
    public function testFallbackParsesTenPrompts(string $prompt, string $expectedIntent): void
    {
        $service = new AdminIntentLlmService(
            new MockHttpClient(static fn (): MockResponse => new MockResponse('')),
            new ArrayAdapter(),
            new NullLogger(),
            '',
            'llama3-70b-8192',
        );

        $result = $service->planFromPrompt($prompt);

        self::assertSame($expectedIntent, $result['intent']);
        self::assertTrue($result['fallback_used']);
    }

    public function testGroqRequestUsesJsonObjectMode(): void
    {
        $capturedOptions = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;

            return new MockResponse(json_encode([
                'choices' => [[
                    'message' => [
                        'content' => '{"intent":"search_users_with_risk_filters","filters":{"status":"ALL"},"limit":100,"reason":"test"}',
                    ],
                ]],
            ], JSON_THROW_ON_ERROR));
        });

        $service = new AdminIntentLlmService(
            $client,
            new ArrayAdapter(),
            new NullLogger(),
            'test-groq-key',
            'llama3-70b-8192',
        );

        $result = $service->planFromPrompt('search all users');

        self::assertSame('search_users_with_risk_filters', $result['intent']);
        self::assertFalse($result['fallback_used']);
        self::assertIsArray($capturedOptions);
        $requestBody = $capturedOptions['json'] ?? null;
        if (!is_array($requestBody)) {
            $decodedBody = json_decode((string) ($capturedOptions['body'] ?? ''), true);
            $requestBody = is_array($decodedBody) ? $decodedBody : [];
        }
        self::assertSame('json_object', $requestBody['response_format']['type'] ?? null);
        self::assertSame('llama3-70b-8192', $requestBody['model'] ?? null);
        self::assertSame(0.1, $requestBody['temperature'] ?? null);
    }

    public function testMalformedGroqResponseFallsBack(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
            'choices' => [[
                'message' => ['content' => 'not a valid json payload'],
            ]],
        ], JSON_THROW_ON_ERROR)));

        $service = new AdminIntentLlmService(
            $client,
            new ArrayAdapter(),
            new NullLogger(),
            'test-groq-key',
            'llama3-70b-8192',
        );

        $result = $service->planFromPrompt('Suspend risky users');

        self::assertSame('bulk_suspend_users', $result['intent']);
        self::assertTrue($result['fallback_used']);
    }

    /**
     * @return iterable<array{0: string, 1: string}>
     */
    public static function promptProvider(): iterable
    {
        yield ['Suspend risky users.', 'bulk_suspend_users'];
        yield ['Do something with all users.', 'search_users_with_risk_filters'];
        yield ['Handle failed logins', 'search_users_with_risk_filters'];
        yield ['Quickly reset pass for my test account', 'reset_user_password'];
        yield ['Export audit for john.doe@example.com', 'export_audit_for_user'];
        yield ['Export audits for high-risk users', 'search_users_with_risk_filters'];
        yield ['Find inactive accounts', 'search_users_with_risk_filters'];
        yield ['Reactivate dormant users', 'bulk_reactivate_users'];
        yield ['List users with risk signals', 'search_users_with_risk_filters'];
        yield ['Reset password for jane@example.com', 'reset_user_password'];
        yield ['Show me users that need review', 'search_users_with_risk_filters'];
    }
}
