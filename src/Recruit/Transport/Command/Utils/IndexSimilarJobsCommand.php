<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Command\Utils;

use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Recruit\Application\Service\JobSimilarIndexerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: self::NAME,
    description: 'Indexe les 3 jobs les plus proches pour chaque job dans Elasticsearch.',
)]
class IndexSimilarJobsCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'recruit:jobs:index-similar';

    public function __construct(
        private readonly JobSimilarIndexerService $jobSimilarIndexerService
    ) {
        parent::__construct();
    }

    /**
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getSymfonyStyle($input, $output);
        $jobsCount = $this->jobSimilarIndexerService->reindexAll();

        if ($input->isInteractive()) {
            $io->success('Similar jobs indexed for ' . $jobsCount . ' jobs.');
        }

        return Command::SUCCESS;
    }
}
