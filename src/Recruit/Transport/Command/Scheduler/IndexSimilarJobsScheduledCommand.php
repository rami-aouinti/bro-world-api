<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Command\Scheduler;

use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Recruit\Transport\Command\Utils\IndexSimilarJobsCommand;
use App\Tool\Application\Service\Scheduler\Interfaces\ScheduledCommandServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: self::NAME,
    description: 'Commande pour créer un cron job horaire d\'indexation des jobs similaires.',
)]
class IndexSimilarJobsScheduledCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'scheduler:recruit-index-similar-jobs';

    public function __construct(
        private readonly ScheduledCommandServiceInterface $scheduledCommandService,
    ) {
        parent::__construct();
    }

    /** @throws Throwable */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getSymfonyStyle($input, $output);
        $message = $this->createScheduledCommand();

        if ($input->isInteractive()) {
            $io->success($message);
        }

        return Command::SUCCESS;
    }

    /** @throws Throwable */
    private function createScheduledCommand(): string
    {
        $entity = $this->scheduledCommandService->findByCommand(IndexSimilarJobsCommand::NAME);

        if ($entity !== null) {
            return "The job RecruitIndexSimilarJobs is already present [id='{$entity->getId()}'] - have a nice day";
        }

        $this->scheduledCommandService->create(
            'Index top 3 similar jobs for each recruit job',
            IndexSimilarJobsCommand::NAME,
            '0 * * * *',
            '/recruit-index-similar-jobs.log'
        );

        return 'The job RecruitIndexSimilarJobs is created - have a nice day';
    }
}
