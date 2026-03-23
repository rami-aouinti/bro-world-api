<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\BootstrapCrmGithubSync;
use App\Crm\Application\Service\CrmGithubBootstrapSyncService;
use App\Crm\Infrastructure\Repository\CrmGithubSyncJobRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

use function count;

#[AsMessageHandler]
final readonly class BootstrapCrmGithubSyncHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private CrmGithubBootstrapSyncService $bootstrapSyncService,
        private CrmGithubSyncJobRepository $syncJobRepository,
    ) {
    }

    public function __invoke(BootstrapCrmGithubSync $message): void
    {
        $job = $this->syncJobRepository->find($message->jobId);
        if ($job !== null) {
            $job->setStatus('running')->setStartedAt(new DateTimeImmutable());
            $this->syncJobRepository->save($job, true);
        }

        $this->logger->info('CRM GitHub bootstrap sync job started.', [
            'jobId' => $message->jobId,
            'applicationSlug' => $message->applicationSlug,
            'owner' => $message->owner,
            'issueTarget' => $message->issueTarget,
            'createPublicProject' => $message->createPublicProject,
            'dryRun' => $message->dryRun,
            'phase' => $message->phase,
        ]);

        try {
            $report = $this->bootstrapSyncService->sync(
                applicationSlug: $message->applicationSlug,
                token: $message->token,
                owner: $message->owner,
                issueTarget: $message->issueTarget,
                createPublicProject: $message->createPublicProject,
                dryRun: $message->dryRun,
                phase: $message->phase,
            );

            if ($job !== null) {
                $errors = $report['errors'] ?? [];
                $job
                    ->setProjectsCreated((int)($report['projects']['created'] ?? 0))
                    ->setReposAttached((int)($report['repositories']['created'] ?? 0) + (int)($report['repositories']['updated'] ?? 0))
                    ->setIssuesImported((int)($report['issues']['created'] ?? 0) + (int)($report['issues']['updated'] ?? 0))
                    ->setErrorsCount(count($errors))
                    ->setErrors(is_array($errors) ? $errors : [])
                    ->setFinishedAt(new DateTimeImmutable())
                    ->setStatus(count($errors) > 0 ? 'failed' : 'completed');

                $this->syncJobRepository->save($job, true);
            }
        } catch (Throwable $exception) {
            if ($job !== null) {
                $job
                    ->setFinishedAt(new DateTimeImmutable())
                    ->setStatus('failed')
                    ->setErrors([$exception->getMessage()])
                    ->setErrorsCount(1);

                $this->syncJobRepository->save($job, true);
            }

            throw $exception;
        }

        $this->logger->info('CRM GitHub bootstrap sync job report saved.', [
            'jobId' => $message->jobId,
            'applicationSlug' => $message->applicationSlug,
            'report' => $report,
        ]);
    }
}
