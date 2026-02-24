<?php

namespace App\MessageHandler;

use App\Entity\AccountNotification;
use App\Message\SendAccountNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use DateTimeImmutable;
use DateTimeZone;

#[AsMessageHandler]
final class SendAccountNotificationHandler
{
    private const CRITICAL_KEYS = [
        'verify_email',
        'reset_requested',
        'password_reset',
    ];

    private const DEFAULT_NOTIFICATION_MATRIX = [
        'new_for_you' => ['email' => true, 'browser' => true, 'app' => true],
        'account_activity' => ['email' => true, 'browser' => true, 'app' => true],
        'new_browser' => ['email' => true, 'browser' => true, 'app' => false],
        'new_device' => ['email' => true, 'browser' => false, 'app' => false],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function __invoke(SendAccountNotification $message): void
    {
        $notification = $this->entityManager->getRepository(AccountNotification::class)->find($message->notificationId);
        if (!$notification instanceof AccountNotification) {
            return;
        }

        if (!in_array($notification->getStatus(), ['PENDING', 'FAILED'], true)) {
            return;
        }

        $notification->incrementAttempts();

        $user = $notification->getUser();
        $payload = $notification->getPayload() ?? [];
        $key = (string) $notification->getKey();

        $decision = $this->shouldSend($key, $payload, $user->getPreferences() ?? [], $user->getTimeZone());
        if (!$decision['allowed']) {
            $notification->setStatus('SKIPPED');
            $notification->setLastError($decision['reason']);
            $this->entityManager->flush();

            return;
        }

        try {
            $email = $this->buildEmail($user->getEmail(), $user->getFirstName() ?? $user->getUserIdentifier(), $key, $payload);
            $this->mailer->send($email);

            $notification->setStatus('SENT');
            $notification->setSentAt(new \DateTimeImmutable());
            $notification->setLastError(null);
        } catch (\Throwable $exception) {
            $notification->setStatus('FAILED');
            $notification->setLastError($exception->getMessage());
            $this->entityManager->flush();

            throw $exception;
        }

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildEmail(string $to, string $name, string $key, array $payload): TemplatedEmail
    {
        $from = (string) $this->parameterBag->get('app.notification_from');
        $context = [
            'name' => $name,
            'key' => $key,
            'payload' => $payload,
            'cta' => null,
        ];

        $subject = 'HomeBalance update';
        $template = 'emails/account_action.html.twig';

        if ($key === 'verify_email') {
            $subject = 'Verify your HomeBalance email';
            $template = 'emails/verify_email.html.twig';
            $context['cta'] = $this->urlGenerator->generate('portal_auth_verify', [
                'token' => $payload['token'] ?? '',
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        } elseif ($key === 'reset_requested' && isset($payload['token'])) {
            $subject = 'Reset your HomeBalance password';
            $template = 'emails/reset_requested.html.twig';
            $context['cta'] = $this->urlGenerator->generate('portal_auth_reset_password', [
                'token' => $payload['token'] ?? '',
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        } elseif ($key === 'welcome') {
            $subject = 'Welcome to HomeBalance';
            $template = 'emails/welcome.html.twig';
            $context['cta'] = $this->urlGenerator->generate('portal_auth_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } elseif (str_starts_with($key, 'family_')) {
            $subject = 'HomeBalance family update';
            $template = 'emails/family_action.html.twig';
            $context['cta'] = $this->urlGenerator->generate('portal_family_settings', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } elseif ($key === 'password_reset') {
            $subject = 'HomeBalance account update';
            $template = 'emails/account_action.html.twig';
            $context['cta'] = $this->urlGenerator->generate('portal_auth_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } elseif (str_contains($key, 'status') || str_contains($key, 'reset')) {
            $subject = 'HomeBalance account update';
            $template = 'emails/admin_action.html.twig';
            $context['cta'] = $this->urlGenerator->generate('portal_account', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return (new TemplatedEmail())
            ->from(new Address($from, 'HomeBalance'))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $preferences
     */
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $preferences
     * @return array{allowed: bool, reason: string}
     */
    private function shouldSend(string $key, array $payload, array $preferences, ?string $timeZone): array
    {
        if (in_array($key, self::CRITICAL_KEYS, true)) {
            return ['allowed' => true, 'reason' => ''];
        }

        if ($this->isQuietHours($preferences, $timeZone)) {
            return ['allowed' => false, 'reason' => 'Muted by quiet hours.'];
        }

        $matrix = $preferences['notifications']['matrix'] ?? self::DEFAULT_NOTIFICATION_MATRIX;
        $type = $this->notificationTypeForKey($key);

        $allowed = (bool) ($matrix[$type]['email'] ?? true);

        return [
            'allowed' => $allowed,
            'reason' => $allowed ? '' : 'Suppressed by preferences.',
        ];
    }

    private function notificationTypeForKey(string $key): string
    {
        if (str_contains($key, 'browser')) {
            return 'new_browser';
        }

        if (str_contains($key, 'device')) {
            return 'new_device';
        }

        if (in_array($key, ['welcome', 'family_created', 'family_joined', 'preferences_updated', 'notifications_updated'], true)) {
            return 'new_for_you';
        }

        return 'account_activity';
    }
    /**
     * @param array<string, mixed> $preferences
     */
    private function isQuietHours(array $preferences, ?string $timeZone): bool
    {
        $quiet = $preferences['communication']['quietHours'] ?? null;
        if (!is_array($quiet)) {
            return false;
        }

        $start = $quiet['start'] ?? null;
        $end = $quiet['end'] ?? null;
        if (!is_string($start) || !is_string($end) || $start === '' || $end === '') {
            return false;
        }

        $tz = new DateTimeZone($timeZone ?: 'UTC');
        $now = new DateTimeImmutable('now', $tz);

        $startTime = DateTimeImmutable::createFromFormat('H:i', $start, $tz);
        $endTime = DateTimeImmutable::createFromFormat('H:i', $end, $tz);

        if (!$startTime || !$endTime) {
            return false;
        }

        $startMinutes = ((int) $startTime->format('H')) * 60 + (int) $startTime->format('i');
        $endMinutes = ((int) $endTime->format('H')) * 60 + (int) $endTime->format('i');
        $nowMinutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');

        if ($startMinutes === $endMinutes) {
            return false;
        }

        if ($startMinutes < $endMinutes) {
            return $nowMinutes >= $startMinutes && $nowMinutes < $endMinutes;
        }

        return $nowMinutes >= $startMinutes || $nowMinutes < $endMinutes;
    }
}

