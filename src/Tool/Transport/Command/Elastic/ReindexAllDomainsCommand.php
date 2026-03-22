<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Elastic;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Index blogs, CRMs, shops, platforms and notifications in Elasticsearch.',
)]
final class ReindexAllDomainsCommand extends Command
{
    final public const string NAME = 'elastic:reindex:all-domains';

    public function __construct(
        private readonly ReindexBlogsCommand $reindexBlogsCommand,
        private readonly ReindexCrmTasksCommand $reindexCrmTasksCommand,
        private readonly ReindexCrmRepositoriesCommand $reindexCrmRepositoriesCommand,
        private readonly ReindexCrmIssuesCommand $reindexCrmIssuesCommand,
        private readonly ReindexShopProductsCommand $reindexShopProductsCommand,
        private readonly ReindexPlatformsCommand $reindexPlatformsCommand,
        private readonly ReindexNotificationsCommand $reindexNotificationsCommand,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->reindexBlogsCommand->run($input, $output);
        $this->reindexCrmTasksCommand->run($input, $output);
        $this->reindexCrmRepositoriesCommand->run($input, $output);
        $this->reindexCrmIssuesCommand->run($input, $output);
        $this->reindexShopProductsCommand->run($input, $output);
        $this->reindexPlatformsCommand->run($input, $output);
        $this->reindexNotificationsCommand->run($input, $output);

        return Command::SUCCESS;
    }
}
