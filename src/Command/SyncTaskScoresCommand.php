<?php

namespace App\Command;

use App\Entity\TaskCompletion;
use App\Message\TaskCompleted;
use App\Service\TaskScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:tasks:scores:sync',
    description: 'Backfills scores from already validated task completions.',
)]
final class SyncTaskScoresCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskScoringService $taskScoringService,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $completions = $this->entityManager->createQueryBuilder()
            ->select('tc')
            ->from(TaskCompletion::class, 'tc')
            ->where('tc.isValidated = true')
            ->orderBy('tc.validatedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $awardedEvents = 0;
        $changedFamilies = [];

        foreach ($completions as $completion) {
            if (!$completion instanceof TaskCompletion) {
                continue;
            }

            $awarded = $this->taskScoringService->awardPointsForValidatedCompletion($completion);
            if ($awarded <= 0) {
                continue;
            }

            ++$awardedEvents;
            $familyId = $completion->getTask()?->getFamily()?->getId();
            if ($familyId !== null) {
                $changedFamilies[$familyId] = true;
            }
        }

        $this->entityManager->flush();

        foreach (array_keys($changedFamilies) as $familyId) {
            $this->messageBus->dispatch(new TaskCompleted(['familyId' => $familyId]));
        }

        $output->writeln(sprintf('Validated completions scanned: %d', count($completions)));
        $output->writeln(sprintf('Score events created: %d', $awardedEvents));
        $output->writeln(sprintf('Families scheduled for badge refresh: %d', count($changedFamilies)));

        return Command::SUCCESS;
    }
}

