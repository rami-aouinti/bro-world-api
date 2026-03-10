<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\General\Application\Message\EntityPatched;
use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\Notification\Application\Message\MarkAllNotificationsAsReadCommand;
use App\Notification\Infrastructure\Repository\NotificationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkAllNotificationsAsReadCommandHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private MessageServiceInterface $messageService,
    ) {
    }

    public function __invoke(MarkAllNotificationsAsReadCommand $command): void
    {
        $updatedCount = $this->notificationRepository->getEntityManager()->getConnection()->transactional(function () use ($command): int {
            return $this->notificationRepository->markAllAsReadByRecipientId($command->actorUserId);
        });

        if ($updatedCount > 0) {
            $this->messageService->sendMessage(new EntityPatched(
                entityType: 'notification',
                entityId: $command->actorUserId,
                context: ['recipientId' => $command->actorUserId, 'updatedCount' => $updatedCount],
            ));
        }
    }
}
