<?php

declare(strict_types=1);

namespace App\Notification\Transport\Command\Scheduler;

use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Notification\Transport\Command\Utils\SyncNotificationTemplatesCommand;
use App\Tool\Application\Service\Scheduler\Interfaces\ScheduledCommandServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: self::NAME,
    description: 'Create cron job for Mailjet notification templates synchronization.',
)]
final class SyncNotificationTemplatesScheduledCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'scheduler:notification-template-sync';

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
        $io = $this->getSymfonyStyle($input, $output);
        $message = $this->createScheduledCommand();

        if ($input->isInteractive()) {
            $io->success($message);
        }

        return Command::SUCCESS;
    }

    /**
     * @throws Throwable
     */
    private function createScheduledCommand(): string
    {
        $entity = $this->scheduledCommandService->findByCommand(SyncNotificationTemplatesCommand::NAME);
        if ($entity !== null) {
            return "The job notification-template-sync is already present [id='{$entity->getId()}'] - have a nice day";
        }

        $this->scheduledCommandService->create(
            'Sync notification templates from Mailjet',
            SyncNotificationTemplatesCommand::NAME,
            '0 * * * *',
            '/notification-template-sync.log',
        );

        return 'The job notification-template-sync is created - have a nice day';
    }
}
