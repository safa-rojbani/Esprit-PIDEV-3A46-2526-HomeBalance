<?php

namespace App\Command;

use App\Message\TaskCompleted;
use App\Service\TaskPenaltyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:tasks:penalties:apply',
    description: 'Apply automatic penalties for overdue tasks and send notifications.',
)]
final class ApplyTaskPenaltiesCommand extends Command
{
    public function __construct(
        private readonly TaskPenaltyService $taskPenaltyService,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->taskPenaltyService->applyMissedPenalties();

        foreach ($result['changedFamilies'] as $familyId) {
            $this->messageBus->dispatch(new TaskCompleted([
                'familyId' => $familyId,
            ]));
        }

        $output->writeln(sprintf('Assignments checked: %d', $result['checked']));
        $output->writeln(sprintf('Penalties applied: %d', $result['penalized']));
        $output->writeln(sprintf('Families refreshed: %d', count($result['changedFamilies'])));

        return Command::SUCCESS;
    }
}
