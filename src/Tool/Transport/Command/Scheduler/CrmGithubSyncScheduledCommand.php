<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Scheduler;

use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Tool\Application\Service\Scheduler\Interfaces\ScheduledCommandServiceInterface;
use App\Tool\Transport\Command\Crm\CrmGithubSyncCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: self::NAME,
    description: 'Create a cron job to run CRM GitHub reconciliation every 30 minutes.',
)]
final class CrmGithubSyncScheduledCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'scheduler:crm-github-sync';

    public function __construct(
        private readonly ScheduledCommandServiceInterface $scheduledCommandService,
    ) {
        parent::__construct();
    }

    /**
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = $this->createScheduledCommand();

        if ($input->isInteractive()) {
            $this->getSymfonyStyle($input, $output)->success($message);
        }

        return Command::SUCCESS;
    }

    /**
     * @throws Throwable
     */
    private function createScheduledCommand(): string
    {
        $entity = $this->scheduledCommandService->findByCommand(CrmGithubSyncCommand::NAME);
        if ($entity !== null) {
            return "The job CrmGithubSync is already present [id='{$entity->getId()}']";
        }

        $this->scheduledCommandService->create(
            'Reconcile CRM GitHub repositories and fix drifts',
            CrmGithubSyncCommand::NAME,
            '*/30 * * * *',
            '/crm-github-sync.log'
        );

        return 'The job CrmGithubSync is created';
    }
}
