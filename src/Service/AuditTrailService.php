<?php

namespace App\Service;

use App\DTO\AuditEvent;
use App\Entity\AuditTrail;
use App\Entity\Family;
use App\Entity\User;
use App\Repository\AuditTrailRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditTrailService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly AuditTrailRepository $auditTrailRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(User $user, string $action, array $payload = [], ?Family $family = null, ?string $channel = 'web'): void
    {
        $trail = new AuditTrail();
        $trail
            ->setUser($user)
            ->setFamily($family)
            ->setAction($action)
            ->setPayload($payload)
            ->setCreatedAt(new DateTimeImmutable())
            ->setUserAgent($this->currentUserAgent())
            ->setIpAddress($this->currentIp())
            ->setChannel($channel);

        $this->entityManager->persist($trail);
        $this->entityManager->flush();
    }

    /**
     * @return list<AuditEvent>
     */
    public function recentForUser(User $user, int $limit = 10): array
    {
        $records = $this->auditTrailRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            $limit,
        );

        return array_map(
            fn (AuditTrail $trail) => new AuditEvent(
                $this->titleFor($trail->getAction()),
                $this->descriptionFor($trail),
                $this->typeFor($trail->getAction()),
                $trail->getCreatedAt(),
            ),
            $records,
        );
    }

    private function currentUserAgent(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->headers->get('User-Agent');
    }

    private function currentIp(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->getClientIp();
    }

    private function titleFor(?string $action): string
    {
        return match ($action) {
            'user.registered' => 'Account created',
            'user.profile.updated' => 'Profile updated',
            'user.status.changed' => 'Status changed',
            'user.login' => 'Signed in',
            'user.password.changed' => 'Password updated',
            'user.preferences.updated' => 'Preferences updated',
            'user.reset.requested' => 'Password reset requested',
            'family.detached' => 'Family access removed',
            'family.invite.resent' => 'Family invite resent',
            default => $action ?? 'Activity',
        };
    }

    private function descriptionFor(AuditTrail $trail): string
    {
        $payload = $trail->getPayload() ?? [];

        return match ($trail->getAction()) {
            'user.registered' => 'User completed self-registration.',
            'user.profile.updated' => 'Profile fields saved: ' . implode(', ', array_keys($payload)),
            'user.status.changed' => sprintf('Status set to %s.', $payload['status'] ?? 'unknown'),
            'user.login' => sprintf('Signed in from %s.', $payload['ip'] ?? 'unknown IP'),
            'user.password.changed' => 'Password updated from account settings.',
            'user.preferences.updated' => sprintf('Communication preferences updated (%s topics).', (string) ($payload['topicCount'] ?? 0)),
            'user.reset.requested' => 'Password reset initiated by an administrator.',
            'family.detached' => sprintf('Removed from %s.', $payload['familyName'] ?? 'their family'),
            'family.invite.resent' => sprintf('Invite resent for %s.', $payload['familyName'] ?? 'the household'),
            default => $payload['note'] ?? 'Activity recorded.',
        };
    }

    private function typeFor(?string $action): string
    {
        return match ($action) {
            'user.registered', 'user.profile.updated' => 'primary',
            'user.status.changed' => 'warning',
            'user.login' => 'success',
            'family.detached' => 'warning',
            'family.invite.resent' => 'info',
            default => 'secondary',
        };
    }
}
