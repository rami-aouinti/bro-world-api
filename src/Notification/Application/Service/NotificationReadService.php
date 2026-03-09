<?php

declare(strict_types=1);

namespace App\Notification\Application\Service;

use App\Notification\Domain\Entity\Notification;

use function array_map;

final readonly class NotificationReadService
{
    /** @param Notification[] $notifications */
    public function normalizeList(array $notifications): array
    {
        return array_map(fn (Notification $notification): array => $this->normalize($notification), $notifications);
    }

    public function normalize(Notification $notification): array
    {
        $from = $notification->getFrom();

        return [
            'id' => $notification->getId(),
            'title' => $notification->getTitle(),
            'description' => $notification->getDescription(),
            'type' => $notification->getType(),
            'read' => $notification->isRead(),
            'createdAt' => $notification->getCreatedAt()?->format(DATE_ATOM),
            'from' => $from === null ? null : [
                'firstName' => $from->getFirstName(),
                'lastName' => $from->getLastName(),
                'photo' => $from->getPhoto(),
            ],
        ];
    }
}
