<?php

declare(strict_types=1);

namespace App\Abonnement\Transport\Command\Scheduler;

use App\Abonnement\Transport\Command\ExecutePendingNewsCommand;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Tool\Application\Service\Scheduler\Interfaces\ScheduledCommandServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: self::NAME,
    description: 'Create a cron job for abonnement news execution every minute.',
)]
final class ExecutePendingNewsScheduledCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'scheduler:abonnement-execute-news';

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
        $entity = $this->scheduledCommandService->findByCommand(ExecutePendingNewsCommand::NAME);

        if ($entity !== null) {
            return "The job AbonnementExecuteNews is already present [id='{$entity->getId()}']";
        }

        $this->scheduledCommandService->create(
            'Execute abonnement news and send emails',
            ExecutePendingNewsCommand::NAME,
            '* * * * *',
            '/abonnement-execute-news.log',
        );

        return 'The job AbonnementExecuteNews is created';
    }
}
