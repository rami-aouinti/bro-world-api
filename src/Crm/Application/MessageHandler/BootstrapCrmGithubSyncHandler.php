<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\BootstrapCrmGithubSync;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BootstrapCrmGithubSyncHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BootstrapCrmGithubSync $message): void
    {
        $this->logger->info('CRM GitHub bootstrap sync job consumed.', [
            'jobId' => $message->jobId,
            'applicationSlug' => $message->applicationSlug,
            'owner' => $message->owner,
            'issueTarget' => $message->issueTarget,
            'createPublicProject' => $message->createPublicProject,
            'dryRun' => $message->dryRun,
        ]);

        // Le traitement de synchronisation GitHub bootstrap sera exécuté ici côté worker.
    }
}
