<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Command\Scheduler;

use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Recruit\Application\Service\ApplicationSlaBreachFinderService;
use App\Recruit\Application\Service\ApplicationSlaReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Détecte les candidatures en dépassement de SLA et relance les recruteurs (email/slack).',
)]
class DetectApplicationsSlaBreachScheduledCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'scheduler:recruit-applications-sla-breach-detect';

    public function __construct(
        private readonly ApplicationSlaBreachFinderService $applicationSlaBreachFinderService,
        private readonly ApplicationSlaReminderService $applicationSlaReminderService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getSymfonyStyle($input, $output);

        $applications = $this->applicationSlaBreachFinderService->findAllBreaches();
        $this->applicationSlaReminderService->sendReminders($applications);

        if ($input->isInteractive()) {
            $io->success(sprintf('SLA check done. %d candidature(s) en dépassement traitée(s).', count($applications)));
        }

        return Command::SUCCESS;
    }
}
