<?php

declare(strict_types=1);

namespace App\Tool\Application\Service\Elastic;

use App\Tool\Application\Service\Elastic\Interfaces\ReindexAllDomainsServiceInterface;
use App\Tool\Transport\Command\Elastic\ReindexBlogsCommand;
use App\Tool\Transport\Command\Elastic\ReindexCrmIssuesCommand;
use App\Tool\Transport\Command\Elastic\ReindexCrmRepositoriesCommand;
use App\Tool\Transport\Command\Elastic\ReindexCrmTasksCommand;
use App\Tool\Transport\Command\Elastic\ReindexNotificationsCommand;
use App\Tool\Transport\Command\Elastic\ReindexPlatformsCommand;
use App\Tool\Transport\Command\Elastic\ReindexShopProductsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReindexAllDomainsService implements ReindexAllDomainsServiceInterface
{
    public function __construct(
        private readonly ReindexBlogsCommand $reindexBlogsCommand,
        private readonly ReindexCrmTasksCommand $reindexCrmTasksCommand,
        private readonly ReindexCrmRepositoriesCommand $reindexCrmRepositoriesCommand,
        private readonly ReindexCrmIssuesCommand $reindexCrmIssuesCommand,
        private readonly ReindexShopProductsCommand $reindexShopProductsCommand,
        private readonly ReindexPlatformsCommand $reindexPlatformsCommand,
        private readonly ReindexNotificationsCommand $reindexNotificationsCommand,
    ) {
    }

    public function reindexAllDomains(InputInterface $input, OutputInterface $output): int
    {
        $codes = [
            $this->reindexBlogsCommand->run($input, $output),
            $this->reindexCrmTasksCommand->run($input, $output),
            $this->reindexCrmRepositoriesCommand->run($input, $output),
            $this->reindexCrmIssuesCommand->run($input, $output),
            $this->reindexShopProductsCommand->run($input, $output),
            $this->reindexPlatformsCommand->run($input, $output),
            $this->reindexNotificationsCommand->run($input, $output),
        ];

        foreach ($codes as $code) {
            if ($code !== Command::SUCCESS) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
