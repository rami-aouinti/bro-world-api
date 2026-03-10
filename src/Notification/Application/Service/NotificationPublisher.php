<?php

declare(strict_types=1);

namespace App\Notification\Application\Service;

use App\Notification\Domain\Entity\Notification;
use App\Notification\Infrastructure\Repository\NotificationRepository;
use App\User\Domain\Entity\User;

final readonly class NotificationPublisher
{
    public function __construct(
        private NotificationRepository $notificationRepository
    ) {
    }

    public function publish(User $from, User $recipient, string $title, string $type, string $description = ''): void
    {
        if ($from->getId() === $recipient->getId()) {
            return;
        }

        $notification = (new Notification())
            ->setTitle($title)
            ->setDescription($description)
            ->setType($type)
            ->setFrom($from)
            ->setRecipient($recipient);

        $this->notificationRepository->save($notification);
    }
}
