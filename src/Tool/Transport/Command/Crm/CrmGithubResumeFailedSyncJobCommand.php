<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Crm;

use App\Crm\Application\Message\BootstrapCrmGithubSync;
use App\Crm\Domain\Entity\CrmGithubSyncJob;
use App\Crm\Infrastructure\Repository\CrmGithubSyncJobRepository;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

use function in_array;
use function is_array;
use function sprintf;

#[AsCommand(
    name: self::NAME,
    description: 'Retry a failed CRM GitHub bootstrap sync job with partial resume support.',
)]
final class CrmGithubResumeFailedSyncJobCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'crm:github:sync:resume-failed-job';

    public function __construct(
        private readonly CrmGithubSyncJobRepository $syncJobRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('jobId', InputArgument::REQUIRED, 'Failed sync job id.')
            ->addOption('token', null, InputOption::VALUE_REQUIRED, 'GitHub token used for resume.')
            ->addOption('phase', null, InputOption::VALUE_REQUIRED, 'Resume phase: full or issues.', 'issues');
    }

    /**
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getSymfonyStyle($input, $output);

        $jobId = (string)$input->getArgument('jobId');
        $token = (string)$input->getOption('token');
        $phase = (string)$input->getOption('phase');

        if ($token === '') {
            $io->error('Option --token is required.');

            return Command::INVALID;
        }

        if (!in_array($phase, ['full', 'issues'], true)) {
            $io->error('Option --phase must be one of: full, issues.');

            return Command::INVALID;
        }

        $failedJob = $this->syncJobRepository->find($jobId);
        if (!$failedJob instanceof CrmGithubSyncJob) {
            $io->error('Sync job not found.');

            return Command::FAILURE;
        }

        if ($failedJob->getStatus() !== 'failed') {
            $io->error(sprintf('Sync job %s is not failed (status=%s).', $failedJob->getId(), $failedJob->getStatus()));

            return Command::FAILURE;
        }

        $parameters = $failedJob->getParameters();
        $issueTarget = is_array($parameters) ? (string)($parameters['issueTarget'] ?? 'task') : 'task';
        $createPublicProject = is_array($parameters) ? (bool)($parameters['createPublicProject'] ?? true) : true;
        $dryRun = is_array($parameters) ? (bool)($parameters['dryRun'] ?? false) : false;

        $newJob = (new CrmGithubSyncJob())
            ->setApplicationSlug($failedJob->getApplicationSlug())
            ->setOwner($failedJob->getOwner())
            ->setStatus('queued')
            ->setParameters([
                'issueTarget' => $issueTarget,
                'createPublicProject' => $createPublicProject,
                'dryRun' => $dryRun,
                'phase' => $phase,
                'resumedFromJobId' => $failedJob->getId(),
            ]);
        $this->syncJobRepository->save($newJob, true);

        $this->messageBus->dispatch(new BootstrapCrmGithubSync(
            jobId: $newJob->getId(),
            applicationSlug: $failedJob->getApplicationSlug(),
            token: $token,
            owner: $failedJob->getOwner(),
            issueTarget: $issueTarget,
            createPublicProject: $createPublicProject,
            dryRun: $dryRun,
            phase: $phase,
        ));

        $io->success(sprintf(
            'Resume job queued. failedJobId=%s newJobId=%s phase=%s',
            $failedJob->getId(),
            $newJob->getId(),
            $phase,
        ));

        return Command::SUCCESS;
    }
}
