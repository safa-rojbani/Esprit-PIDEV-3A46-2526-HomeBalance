<?php

namespace App\Command;

use App\Entity\AuditTrail;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:prune-audit-trail',
    description: 'Prune audit trail records older than retention window.'
)]
final class PruneAuditTrailCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $this->parameterBag->get('app.audit_retention_days');
        $cutoff = (new DateTimeImmutable())->sub(new DateInterval(sprintf('P%dD', max(1, $days))));

        $qb = $this->entityManager->createQueryBuilder();
        $deleted = $qb
            ->delete(AuditTrail::class, 'a')
            ->andWhere('a.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        $io->success(sprintf('Pruned %d audit records older than %d days.', $deleted, $days));

        return Command::SUCCESS;
    }
}
