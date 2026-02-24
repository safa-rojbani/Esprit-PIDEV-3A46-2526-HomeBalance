<?php

namespace App\Service;

use App\DTO\PreferenceUpdateResult;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class PreferencesService
{
    /**
     * @var array<string, array{label: string, description: string}>
     */
    public const TOPICS = [
        'budget' => [
            'label' => 'Budget pulse',
            'description' => 'Expense spikes, savings drift, and cashflow surprises.',
        ],
        'tasks' => [
            'label' => 'Chores & routines',
            'description' => 'When assignments fall behind or routines lapse.',
        ],
        'documents' => [
            'label' => 'Documents & renewals',
            'description' => 'License renewals, insurance proofs, and shared files.',
        ],
        'wellbeing' => [
            'label' => 'Wellbeing check-ins',
            'description' => 'Mood check prompts, family reflections, and nudges to pause.',
        ],
    ];

    /**
     * @var array<string, array{label: string, description: string, icon: string}>
     */
    public const CHANNELS = [
        'email' => [
            'label' => 'Email',
            'description' => 'For fuller context, summaries, and links.',
            'icon' => 'bx bx-envelope',
        ],
        'push' => [
            'label' => 'Mobile push',
            'description' => 'Fast nudges when you are on the move.',
            'icon' => 'bx bx-mobile-alt',
        ],
        'sms' => [
            'label' => 'Text message',
            'description' => 'Short alerts for critical signals only.',
            'icon' => 'bx bx-message-dots',
        ],
        'browser' => [
            'label' => 'Browser notice',
            'description' => 'Native notifications while signed in on desktop.',
            'icon' => 'bx bx-bell',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditTrailService $auditTrailService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @return array{
     *     topics: list<string>,
     *     channels: list<string>,
     *     quietStart: ?string,
     *     quietEnd: ?string,
     *     weeklySummary: bool,
     *     updatedAt: ?string
     * }
     */
    public function viewDataFor(User $user): array
    {
        $communication = $user->getPreferences()['communication'] ?? [];

        return [
            'topics' => $communication['topics'] ?? ['tasks', 'budget'],
            'channels' => $communication['channels'] ?? ['email'],
            'quietStart' => $communication['quietHours']['start'] ?? null,
            'quietEnd' => $communication['quietHours']['end'] ?? null,
            'weeklySummary' => (bool) ($communication['weeklySummary'] ?? false),
            'updatedAt' => $communication['updatedAt'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $submitted
     */
    public function savePreferences(User $user, array $submitted): PreferenceUpdateResult
    {
        $data = $this->sanitize($submitted);
        $data['updatedAt'] = $user->getPreferences()['communication']['updatedAt'] ?? null;
        $errors = $this->validate($data);

        if ($errors !== []) {
            return new PreferenceUpdateResult(false, $data, $errors);
        }

        $timestamp = (new DateTimeImmutable())->format(DATE_ATOM);
        $payload = [
            'topics' => $data['topics'],
            'channels' => $data['channels'],
            'quietHours' => [
                'start' => $data['quietStart'],
                'end' => $data['quietEnd'],
            ],
            'weeklySummary' => $data['weeklySummary'],
            'updatedAt' => $timestamp,
        ];

        $preferences = $user->getPreferences() ?? [];
        $preferences['communication'] = $payload;

        $user->setPreferences($preferences);
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        $context = [
            'topicCount' => count($payload['topics']),
            'channels' => $payload['channels'],
        ];

        $this->auditTrailService->record($user, 'user.preferences.updated', $context);
        $this->notificationService->sendAccountNotification($user, 'preferences_updated', $context);

        $data['updatedAt'] = $timestamp;

        return new PreferenceUpdateResult(true, $data);
    }

    /**
     * @param array<string, mixed> $submitted
     * @return array{
     *     topics: list<string>,
     *     channels: list<string>,
     *     quietStart: ?string,
     *     quietEnd: ?string,
     *     weeklySummary: bool,
     *     updatedAt: ?string
     * }
     */
    private function sanitize(array $submitted): array
    {
        $topicKeys = array_map('strval', array_keys(self::TOPICS));
        $channelKeys = array_map('strval', array_keys(self::CHANNELS));

        $topics = array_values(array_intersect(
            array_map('strval', (array) ($submitted['topics'] ?? [])),
            $topicKeys,
        ));
        $channels = array_values(array_intersect(
            array_map('strval', (array) ($submitted['channels'] ?? [])),
            $channelKeys,
        ));

        return [
            'topics' => $topics,
            'channels' => $channels,
            'quietStart' => $this->toNullableString($submitted['quietStart'] ?? null),
            'quietEnd' => $this->toNullableString($submitted['quietEnd'] ?? null),
            'weeklySummary' => filter_var($submitted['weeklySummary'] ?? false, FILTER_VALIDATE_BOOL),
            'updatedAt' => null,
        ];
    }

    /**
     * @param array{
     *     topics: list<string>,
     *     channels: list<string>,
     *     quietStart: ?string,
     *     quietEnd: ?string,
     *     weeklySummary: bool,
     *     updatedAt: ?string
     * } $data
     * @return list<string>
     */
    private function validate(array &$data): array
    {
        $errors = [];

        if ($data['topics'] === []) {
            $errors[] = 'Select at least one topic to track.';
        }

        if ($data['channels'] === []) {
            $errors[] = 'Choose at least one channel so we know how to reach you.';
        }

        $startInput = $data['quietStart'];
        $endInput = $data['quietEnd'];
        $startNormalized = $this->normalizeTime($startInput);
        $endNormalized = $this->normalizeTime($endInput);

        if ($startInput !== null && $startNormalized === null) {
            $errors[] = 'Quiet hours start time must use the HH:MM format.';
        } elseif ($startNormalized !== null) {
            $data['quietStart'] = $startNormalized;
        }

        if ($endInput !== null && $endNormalized === null) {
            $errors[] = 'Quiet hours end time must use the HH:MM format.';
        } elseif ($endNormalized !== null) {
            $data['quietEnd'] = $endNormalized;
        }

        if (($startInput !== null) xor ($endInput !== null)) {
            $errors[] = 'Provide both quiet-hour values or leave them blank.';
        }

        return $errors;
    }

    private function normalizeTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!preg_match('/^(?<hour>\d{1,2})(?::(?<minute>\d{2}))?$/', $value, $matches)) {
            return null;
        }

        $hour = (int) $matches['hour'];
        $minute = isset($matches['minute']) ? (int) $matches['minute'] : 0;

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
