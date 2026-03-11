<?php

declare(strict_types=1);

namespace App\Calendar\Application\MessageHandler;

use App\Calendar\Application\Message\PatchEventCommand;
use App\Calendar\Domain\Entity\Event;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PatchEventCommandHandler
{
    public function __construct(
        private EventRepository $eventRepository,
        private ApplicationRepository $applicationRepository,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(PatchEventCommand $command): void
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

            if ($command->title !== null) {
                $event->setTitle($command->title);
            }
            if ($command->description !== null) {
                $event->setDescription($command->description);
            }
            if ($command->startAt !== null) {
                $event->setStartAt($command->startAt);
            }
            if ($command->endAt !== null) {
                $event->setEndAt($command->endAt);
            }
            if ($command->visibility !== null) {
                $event->setVisibility($command->visibility);
            }
            if ($command->location !== null) {
                $event->setLocation($command->location);
            }
            if ($command->isAllDay !== null) {
                $event->setIsAllDay($command->isAllDay);
            }
            if ($command->timezone !== null) {
                $event->setTimezone($command->timezone);
            }
            if ($command->url !== null) {
                $event->setUrl($command->url);
            }
            if ($command->color !== null) {
                $event->setColor($command->color);
            }
            if ($command->backgroundColor !== null) {
                $event->setBackgroundColor($command->backgroundColor);
            }
            if ($command->borderColor !== null) {
                $event->setBorderColor($command->borderColor);
            }
            if ($command->textColor !== null) {
                $event->setTextColor($command->textColor);
            }
            if ($command->organizerName !== null) {
                $event->setOrganizerName($command->organizerName);
            }
            if ($command->organizerEmail !== null) {
                $event->setOrganizerEmail($command->organizerEmail);
            }
            if ($command->attendees !== null) {
                $event->setAttendees($command->attendees);
            }
            if ($command->rrule !== null) {
                $event->setRrule($command->rrule);
            }
            if ($command->recurrenceExceptions !== null) {
                $event->setRecurrenceExceptions($command->recurrenceExceptions);
            }
            if ($command->recurrenceEndAt !== null) {
                $event->setRecurrenceEndAt($command->recurrenceEndAt);
            }
            if ($command->recurrenceCount !== null) {
                $event->setRecurrenceCount($command->recurrenceCount);
            }
            if ($command->reminders !== null) {
                $event->setReminders($command->reminders);
            }
            if ($command->metadata !== null) {
                $event->setMetadata($command->metadata);
            }

            $this->eventRepository->save($event);
        });

        $this->cacheInvalidationService->invalidateEventCaches($command->applicationSlug, $command->actorUserId);
    }
}
