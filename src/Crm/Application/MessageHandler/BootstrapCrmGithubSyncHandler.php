<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\BootstrapCrmGithubSync;
use App\Crm\Application\Service\CrmGithubBootstrapSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BootstrapCrmGithubSyncHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private CrmGithubBootstrapSyncService $bootstrapSyncService,
    ) {
    }

    public function __invoke(BootstrapCrmGithubSync $message): void
    {
        $this->logger->info('CRM GitHub bootstrap sync job started.', [
            'jobId' => $message->jobId,
            'applicationSlug' => $message->applicationSlug,
            'owner' => $message->owner,
            'issueTarget' => $message->issueTarget,
            'createPublicProject' => $message->createPublicProject,
            'dryRun' => $message->dryRun,
        ]);


        $report = $this->bootstrapSyncService->sync(
            applicationSlug: $message->applicationSlug,
            token: $message->token,
            owner: $message->owner,
            issueTarget: $message->issueTarget,
            createPublicProject: $message->createPublicProject,
            dryRun: $message->dryRun,
        );

        $this->logger->info('CRM GitHub bootstrap sync job report saved.', [
            'jobId' => $message->jobId,
            'applicationSlug' => $message->applicationSlug,
            'report' => $report,
        ]);
    }
}
