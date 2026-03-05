<?php

namespace App\MessageHandler\SMS;

use App\Entity\User;
use App\Message\SMS\SendSmsFallbackMessage;
use App\Repository\UserRepository;
use App\Service\AuditTrailService;
use App\ServiceModuleMessagerie\SMS\TwilioClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsMessageHandler]
class SendSmsFallbackHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TwilioClient $twilioClient,
        private readonly AuditTrailService $auditTrailService,
        private readonly RequestStack $requestStack,
        private readonly int $offlineThresholdMinutes = 5,
    ) {
    }

    public function __invoke(SendSmsFallbackMessage $message): void
    {
        $recipient = $this->userRepository->find($message->getRecipientUserId());
        
        if (!$recipient instanceof User) {
            return;
        }

        // Check 1: Does user have a phone number?
        $preferences = $recipient->getPreferences() ?? [];
        $profile = is_array($preferences['profile'] ?? null) ? $preferences['profile'] : [];
        $phoneNumber = trim((string) ($profile['phoneNumber'] ?? ''));
        if (empty($phoneNumber)) {
            $this->recordSkip($recipient, 'no_phone');
            return;
        }

        // Check 2: Approximate online state from recent activity.
        $lastLogin = $recipient->getLastLogin();
        if ($lastLogin instanceof \DateTimeInterface) {
            $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d minutes', $this->offlineThresholdMinutes));
            if ($lastLogin >= $cutoff) {
                $this->recordSkip($recipient, 'online');
                return;
            }
        }

        // Check 3: Is SMS channel enabled in notification matrix?
        $channels = $preferences['communication']['channels'] ?? [];
        if (!in_array('sms', $channels, true)) {
            $this->recordSkip($recipient, 'disabled');
            return;
        }

        // Check 4: Is user in quiet hours?
        if ($this->isInQuietHours($recipient)) {
            $this->recordSkip($recipient, 'quiet_hours');
            return;
        }

        // Build SMS message
        $appUrl = $this->getAppUrl();
        $smsMessage = sprintf(
            "HomeBalance: New message from %s.\nLog in to reply: %s/portal/messaging/%d",
            $message->getSenderName(),
            $appUrl,
            $message->getConversationId()
        );

        // Send SMS
        $success = $this->twilioClient->send($phoneNumber, $smsMessage);

        if ($success) {
            $this->auditTrailService->record(
                $recipient,
                'sms.sent',
                [
                    'recipientId' => $recipient->getId(),
                    'conversationId' => $message->getConversationId(),
                ],
                $recipient->getFamily()
            );
        } else {
            $this->auditTrailService->record(
                $recipient,
                'sms.failed',
                [
                    'recipientId' => $recipient->getId(),
                    'conversationId' => $message->getConversationId(),
                ],
                $recipient->getFamily()
            );
        }
    }

    private function isInQuietHours(User $user): bool
    {
        $preferences = $user->getPreferences() ?? [];
        $quietHours = $preferences['communication']['quietHours'] ?? [];
        
        $quietStart = $quietHours['start'] ?? null;
        $quietEnd = $quietHours['end'] ?? null;

        if (!$quietStart || !$quietEnd) {
            return false;
        }

        // Get user's timezone
        $timezone = $user->getTimeZone() ?? 'UTC';
        $now = new \DateTime('now', new \DateTimeZone($timezone));
        $currentTime = (int) $now->format('Hi');

        $startTime = (int) str_replace(':', '', $quietStart);
        $endTime = (int) str_replace(':', '', $quietEnd);

        // Handle overnight quiet hours (e.g., 22:00 to 08:00)
        if ($startTime > $endTime) {
            return $currentTime >= $startTime || $currentTime <= $endTime;
        }

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    private function recordSkip(User $user, string $reason): void
    {
        $this->auditTrailService->record(
            $user,
            'sms.skipped',
            ['reason' => $reason],
            $user->getFamily()
        );
    }

    private function getAppUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $request->getScheme() . '://' . $request->getHost();
        }
        
        return $_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? 'https://homebalance.app';
    }
}
