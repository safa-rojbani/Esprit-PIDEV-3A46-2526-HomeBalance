<?php

namespace App\Command;

use App\Entity\Family;
use App\Service\Ai\WeeklyInsightsOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:tasks:ai:weekly-insights',
    description: 'Generate weekly AI summary and child engagement insights.',
)]
final class GenerateWeeklyInsightsCommand extends Command
{
    public function __construct(
        private readonly WeeklyInsightsOrchestrator $weeklyInsightsOrchestrator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('family-id', null, InputOption::VALUE_OPTIONAL, 'Generate for one family id')
            ->addOption('week-start', null, InputOption::VALUE_OPTIONAL, 'Week start date (Y-m-d)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force regeneration even if insight exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool) $input->getOption('force');
        $weekStart = $this->parseDate((string) $input->getOption('week-start'));
        $familyId = $input->getOption('family-id');

        if ($familyId !== null && $familyId !== '') {
            $family = $this->entityManager->getRepository(Family::class)->find((int) $familyId);
            if (!$family instanceof Family) {
                $output->writeln('<error>Family not found.</error>');

                return Command::FAILURE;
            }

            $insight = $this->weeklyInsightsOrchestrator->generateForFamily($family, $weekStart, $force);
            $output->writeln(sprintf(
                'Insight generated for family #%d (%s) - status: %s',
                (int) $family->getId(),
                (string) $family->getName(),
                (string) $insight->getStatus()
            ));

            return Command::SUCCESS;
        }

        $count = $this->weeklyInsightsOrchestrator->generateForAllFamilies($weekStart, $force);
        $output->writeln(sprintf('Weekly insights generated for %d families.', $count));

        return Command::SUCCESS;
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date instanceof \DateTimeImmutable) {
            return $date->setTime(0, 0, 0);
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
