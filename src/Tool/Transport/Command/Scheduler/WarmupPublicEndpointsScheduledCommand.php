<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Scheduler;

use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Tool\Application\Service\Scheduler\Interfaces\ScheduledCommandServiceInterface;
use App\Tool\Transport\Command\WarmupPublicEndpointsCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: self::NAME,
    description: 'Create a cron job to warm up public endpoints every 30 minutes.',
)]
final class WarmupPublicEndpointsScheduledCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'scheduler:warmup-public-endpoints';

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
        $entity = $this->scheduledCommandService->findByCommand(WarmupPublicEndpointsCommand::NAME);

        if ($entity !== null) {
            return "The job WarmupPublicEndpoints is already present [id='{$entity->getId()}']";
        }

        $this->scheduledCommandService->create(
            'Warm up public endpoints',
            WarmupPublicEndpointsCommand::NAME,
            '*/30 * * * *',
            '/warmup-public-endpoints.log'
        );

        return 'The job WarmupPublicEndpoints is created';
    }
}
