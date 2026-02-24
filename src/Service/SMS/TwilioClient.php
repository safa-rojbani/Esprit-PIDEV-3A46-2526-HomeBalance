<?php

namespace App\Service\SMS;

use App\Exception\TwilioException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TwilioClient
{
    private const BASE_URL = 'https://api.twilio.com/2010-04-01/Accounts';

    private readonly HttpClientInterface $httpClient;
    private readonly string $accountSid;
    private readonly string $authToken;
    private readonly string $fromNumber;
    private readonly ?LoggerInterface $logger;

    public function __construct(
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
        $this->accountSid = $_ENV['TWILIO_ACCOUNT_SID'] ?? $_SERVER['TWILIO_ACCOUNT_SID'] ?? '';
        $this->authToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? $_SERVER['TWILIO_AUTH_TOKEN'] ?? '';
        $this->fromNumber = $_ENV['TWILIO_FROM_NUMBER'] ?? $_SERVER['TWILIO_FROM_NUMBER'] ?? '';
        $this->logger = $logger;

        if (empty($this->accountSid) || empty($this->authToken) || empty($this->fromNumber)) {
            throw new \InvalidArgumentException('Twilio credentials are not properly configured');
        }
    }

    /**
     * Send an SMS message.
     *
     * @param string $toNumber The recipient's phone number (E.164 format, e.g., +1234567890)
     * @param string $message The message body
     * @return bool True on success, false on failure
     */
    public function send(string $toNumber, string $message): bool
    {
        $url = self::BASE_URL . '/' . $this->accountSid . '/Messages.json';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$this->accountSid, $this->authToken],
                'body' => [
                    'From' => $this->fromNumber,
                    'To' => $toNumber,
                    'Body' => $message,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger?->info('SMS sent successfully', [
                    'to' => $toNumber,
                    'from' => $this->fromNumber,
                ]);
                return true;
            }

            // Handle error response
            $content = $response->toArray();
            $errorMessage = $content['message'] ?? 'Unknown Twilio error';
            
            $this->logger?->error('Twilio SMS failed', [
                'to' => $toNumber,
                'status' => $statusCode,
                'error' => $errorMessage,
            ]);

            return false;

        } catch (ClientExceptionInterface|DecodingExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger?->error('Twilio HTTP error', [
                'to' => $toNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger?->error('Twilio unexpected error', [
                'to' => $toNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate a phone number format (basic E.164 validation).
     *
     * @param string $phoneNumber
     * @return bool
     */
    public function validatePhoneNumber(string $phoneNumber): bool
    {
        // Basic E.164 format: + and 1-15 digits
        return (bool) preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber);
    }
}
