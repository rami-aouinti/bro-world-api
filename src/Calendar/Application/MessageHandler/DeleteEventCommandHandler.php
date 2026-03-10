<?php

declare(strict_types=1);

namespace App\Calendar\Application\MessageHandler;

use App\Calendar\Application\Message\DeleteEventCommand;
use App\Calendar\Domain\Entity\Event;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteEventCommandHandler
{
    public function __construct(
        private EventRepository $eventRepository,
        private ApplicationRepository $applicationRepository,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(DeleteEventCommand $command): void
    {
        $entityManager = $this->eventRepository->getEntityManager();
        $entityManager->getConnection()->transactional(function () use ($command): void {
            $event = $this->eventRepository->find($command->eventId);
            if (!$event instanceof Event || $event->getUser()?->getId() !== $command->actorUserId) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Event not found.');
            }

            if ($command->applicationSlug !== null) {
                $application = $this->applicationRepository->findOneBy([
                    'slug' => $command->applicationSlug,
                ]);
                if (!$application instanceof Application || $application->getUser()?->getId() !== $command->actorUserId) {
                    throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Application not found.');
                }

                if ($event->getCalendar()?->getApplication()?->getId() !== $application->getId()) {
                    throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Event not found.');
                }
            }

            $this->eventRepository->remove($event);
        });

        $this->cacheInvalidationService->invalidateEventCaches($command->applicationSlug, $command->actorUserId);
    }
}
