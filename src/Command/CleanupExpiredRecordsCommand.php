<?php

namespace App\Command;

use App\Entity\Family;
use App\Entity\FamilyInvitation;
use App\Entity\User;
use App\Enum\InvitationStatus;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-expired-records',
    description: 'Clean up expired join codes, invitations, and verification tokens.'
)]
final class CleanupExpiredRecordsCommand extends Command
{
    private const VERIFICATION_TTL = 'PT48H';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new DateTimeImmutable();

        $expiredCodes = $this->expireJoinCodes($now);
        $expiredInvites = $this->expireInvitations($now);
        $clearedTokens = $this->clearVerificationTokens($now);

        $this->entityManager->flush();

        $io->success(sprintf(
            'Cleanup complete. Join codes cleared: %d, invitations expired: %d, verification tokens cleared: %d.',
            $expiredCodes,
            $expiredInvites,
            $clearedTokens,
        ));

        return Command::SUCCESS;
    }

    private function expireJoinCodes(DateTimeImmutable $now): int
    {
        $families = $this->entityManager->getRepository(Family::class)
            ->createQueryBuilder('f')
            ->andWhere('f.codeExpiresAt IS NOT NULL')
            ->andWhere('f.codeExpiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($families as $family) {
            if (!$family instanceof Family) {
                continue;
            }

            $family
                ->setJoinCode(null)
                ->setCodeExpiresAt(null)
                ->setUpdatedAt($now);
            $count++;
        }

        return $count;
    }

    private function expireInvitations(DateTimeImmutable $now): int
    {
        $invitations = $this->entityManager->getRepository(FamilyInvitation::class)
            ->createQueryBuilder('fi')
            ->andWhere('fi.status = :status')
            ->andWhere('fi.expiresAt IS NOT NULL')
            ->andWhere('fi.expiresAt <= :now')
            ->setParameter('status', InvitationStatus::PENDING->value)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($invitations as $invitation) {
            if (!$invitation instanceof FamilyInvitation) {
                continue;
            }

            $invitation->setStatus(InvitationStatus::EXPIRED);
            $count++;
        }

        return $count;
    }

    private function clearVerificationTokens(DateTimeImmutable $now): int
    {
        $expiry = $now->sub(new DateInterval(self::VERIFICATION_TTL));

        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->andWhere('u.emailVerifiedAt IS NULL')
            ->andWhere('u.emailVerificationRequestedAt IS NOT NULL')
            ->andWhere('u.emailVerificationRequestedAt <= :expiry')
            ->setParameter('expiry', $expiry)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $user->setEmailVerificationToken(null);
            $user->setEmailVerificationRequestedAt(null);
            $count++;
        }

        return $count;
    }
}
