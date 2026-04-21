<?php

namespace App\Command;

use App\Service\Projet\TaskDeadlineAlertService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:projects:send-task-reminders',
    description: 'Envoie les alertes email des taches en retard ou echeance J+1 (08:00).',
)]
final class SendTaskDeadlineAlertsCommand extends Command
{
    public function __construct(private readonly TaskDeadlineAlertService $taskDeadlineAlertService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule le traitement sans envoyer d\'emails.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $summary = $this->taskDeadlineAlertService->sendAlertsForAllRh($dryRun);

        $io->success(sprintf(
            'Alertes traitees. RH=%d, taches=%d, emails employes=%d, emails RH=%d',
            $summary['rhProcessed'],
            $summary['tasksFlagged'],
            $summary['employeeEmailsSent'],
            $summary['rhEmailsSent']
        ));

        if ($dryRun) {
            $io->note('Mode dry-run: aucun email n\'a ete envoye.');
        }

        return Command::SUCCESS;
    }
}


