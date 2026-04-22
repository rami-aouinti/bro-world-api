<?php

declare(strict_types=1);

namespace App\Notification\Application\Service;

use App\General\Application\Service\MercurePublisher;
use App\Notification\Domain\Entity\Notification;
use App\Notification\Infrastructure\Repository\NotificationRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;

final readonly class NotificationPublisher
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private MercurePublisher $mercurePublisher,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws JsonException
     * @throws ORMException
     */
    public function publish(User $from, User $recipient, string $title, string $type, string $description = ''): void
    {
        if ($from->getId() === $recipient->getId()) {
            return;
        }

        $notification = new Notification()
            ->setTitle($title)
            ->setDescription($description)
            ->setType($type)
            ->setFrom($from)
            ->setRecipient($recipient);

        $this->notificationRepository->save($notification);

        $this->mercurePublisher->publish('/users/' . $recipient->getId() . '/notifications', [
            'id' => $notification->getId(),
            'title' => $notification->getTitle(),
            'description' => $notification->getDescription(),
            'type' => $notification->getType(),
            'recipientId' => $recipient->getId(),
            'fromId' => $from->getId(),
            'createdAt' => $notification->getCreatedAt()?->format(DATE_ATOM)
        ]);
    }
}
