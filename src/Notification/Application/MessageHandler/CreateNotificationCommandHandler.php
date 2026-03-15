<?php

declare(strict_types=1);

namespace App\Notification\Application\MessageHandler;

use App\General\Application\Message\EntityCreated;
use App\General\Application\Service\MercurePublisher;
use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\Notification\Application\Message\CreateNotificationCommand;
use App\Notification\Domain\Entity\Notification;
use App\Notification\Infrastructure\Repository\NotificationRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

use function trim;

#[AsMessageHandler]
final readonly class CreateNotificationCommandHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private UserRepository $userRepository,
        private MessageServiceInterface $messageService,
        private MercurePublisher $mercurePublisher,
    ) {
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function __invoke(CreateNotificationCommand $command): void
    {
        $entityManager = $this->notificationRepository->getEntityManager();

        $notification = $entityManager->getConnection()->transactional(function () use ($command): Notification {
            $recipient = $this->userRepository->find($command->recipientId);
            if (!$recipient instanceof User) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Recipient user not found.');
            }

            $from = null;
            if ($command->fromId !== null) {
                $from = $this->userRepository->find($command->fromId);
                if (!$from instanceof User) {
                    throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Sender user not found.');
                }
            }

            $notification = (new Notification())
                ->setTitle(trim($command->title))
                ->setDescription(trim($command->description))
                ->setType(trim($command->type))
                ->setRecipient($recipient)
                ->setFrom($from);

            $this->notificationRepository->save($notification);

            return $notification;
        });

        $this->messageService->sendMessage(new EntityCreated(
            entityType: 'notification',
            entityId: $notification->getId(),
            context: [
                'recipientId' => $notification->getRecipient()->getId(),
            ],
        ));

        $this->mercurePublisher->publish('/users/' . $notification->getRecipient()->getId() . '/notifications', [
            'id' => $notification->getId(),
            'title' => $notification->getTitle(),
            'description' => $notification->getDescription(),
            'type' => $notification->getType(),
            'recipientId' => $notification->getRecipient()->getId(),
            'fromId' => $notification->getFrom()?->getId(),
            'createdAt' => $notification->getCreatedAt()?->format(DATE_ATOM),
        ]);
    }
}
