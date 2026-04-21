<?php

declare(strict_types=1);

namespace App\Tool\Application\Service\Elastic\Interfaces;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface ReindexAllDomainsServiceInterface
{
    public function reindexAllDomains(InputInterface $input, OutputInterface $output): int;
}
