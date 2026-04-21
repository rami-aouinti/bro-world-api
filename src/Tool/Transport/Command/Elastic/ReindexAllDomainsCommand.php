<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Elastic;

use App\Tool\Application\Service\Elastic\Interfaces\ReindexAllDomainsServiceInterface;
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
        private readonly ReindexAllDomainsServiceInterface $reindexAllDomainsService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->reindexAllDomainsService->reindexAllDomains($input, $output);
    }
}
