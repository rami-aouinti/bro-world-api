<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Elastic;

use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Notification\Application\Projection\NotificationProjection;
use App\Notification\Domain\Entity\Notification;
use App\Notification\Infrastructure\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Index notifications in Elasticsearch.',
)]
final class ReindexNotificationsCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'elastic:reindex:notifications';

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexed = 0;

        /** @var Notification $notification */
        foreach ($this->notificationRepository->findBy([], ['createdAt' => 'DESC']) as $notification) {
            $this->elasticsearchService->index(NotificationProjection::INDEX_NAME, $notification->getId(), [
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'description' => $notification->getDescription(),
                'type' => $notification->getType(),
                'read' => $notification->isRead(),
                'recipientId' => $notification->getRecipient()->getId(),
                'fromId' => $notification->getFrom()?->getId(),
                'updatedAt' => $notification->getUpdatedAt()?->format(DATE_ATOM),
            ]);
            ++$indexed;
        }

        if ($input->isInteractive()) {
            $this->getSymfonyStyle($input, $output)->success('Notifications indexed: ' . $indexed);
        }

        return Command::SUCCESS;
    }
}
