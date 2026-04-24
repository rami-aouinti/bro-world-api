<?php

declare(strict_types=1);

namespace App\Notification\Transport\Command\Utils;

use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Notification\Application\Service\NotificationTemplateSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function sprintf;

#[AsCommand(
    name: self::NAME,
    description: 'Synchronize notification templates from Mailjet.',
)]
final class SyncNotificationTemplatesCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'notification:templates:sync';

    public function __construct(
        private readonly NotificationTemplateSyncService $notificationTemplateSyncService,
    ) {
        parent::__construct();
    }

    /**
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getSymfonyStyle($input, $output);
        $synced = $this->notificationTemplateSyncService->sync();

        if ($input->isInteractive()) {
            $io->success(sprintf('%d notification templates synchronized.', $synced));
        }

        return Command::SUCCESS;
    }
}
