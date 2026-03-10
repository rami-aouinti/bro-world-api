<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Scheduler;

use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Tool\Application\Service\Scheduler\Interfaces\ScheduledCommandServiceInterface;
use App\Tool\Transport\Command\Elastic\ReindexAllDomainsCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: self::NAME,
    description: 'Command to create a cron job to reindex blogs, crms, shops, platforms and notifications in Elasticsearch every 30 minutes.',
)]
final class ReindexElasticDomainsScheduledCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'scheduler:elastic-reindex-domains';

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
        $entity = $this->scheduledCommandService->findByCommand(ReindexAllDomainsCommand::NAME);

        if ($entity !== null) {
            return "The job ElasticReindexDomains is already present [id='{$entity->getId()}']";
        }

        $this->scheduledCommandService->create(
            'Reindex blogs, crms, shops, platforms and notifications in Elasticsearch',
            ReindexAllDomainsCommand::NAME,
            '*/30 * * * *',
            '/elastic-reindex-domains.log'
        );

        return 'The job ElasticReindexDomains is created';
    }
}
