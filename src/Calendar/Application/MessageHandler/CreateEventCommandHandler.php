<?php

declare(strict_types=1);

namespace App\Calendar\Application\MessageHandler;

use App\Calendar\Application\Message\CreateEventCommand;
use App\Calendar\Domain\Entity\Event;
use App\Calendar\Infrastructure\Repository\CalendarRepository;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateEventCommandHandler
{
    public function __construct(
        private EventRepository $eventRepository,
        private UserRepository $userRepository,
        private ApplicationRepository $applicationRepository,
        private CalendarRepository $calendarRepository,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(CreateEventCommand $command): void
    {
        $entityManager = $this->eventRepository->getEntityManager();
        $entityManager->getConnection()->transactional(function () use ($command): void {
            $user = $this->userRepository->find($command->actorUserId);
            if (!$user instanceof User) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'User not found.');
            }

            $event = (new Event())
                ->setTitle($command->title)
                ->setDescription($command->description)
                ->setStartAt($command->startAt)
                ->setEndAt($command->endAt)
                ->setStatus($command->status)
                ->setVisibility($command->visibility)
                ->setUser($user)
                ->setLocation($command->location)
                ->setIsAllDay($command->isAllDay)
                ->setTimezone($command->timezone)
                ->setUrl($command->url)
                ->setColor($command->color)
                ->setBackgroundColor($command->backgroundColor)
                ->setBorderColor($command->borderColor)
                ->setTextColor($command->textColor)
                ->setOrganizerName($command->organizerName)
                ->setOrganizerEmail($command->organizerEmail)
                ->setAttendees($command->attendees)
                ->setRrule($command->rrule)
                ->setRecurrenceExceptions($command->recurrenceExceptions)
                ->setRecurrenceEndAt($command->recurrenceEndAt)
                ->setRecurrenceCount($command->recurrenceCount)
                ->setReminders($command->reminders)
                ->setMetadata($command->metadata);

            if ($command->applicationSlug !== null) {
                $application = $this->applicationRepository->findOneBy([
                    'slug' => $command->applicationSlug,
                ]);
                if (!$application instanceof Application || $application->getUser()?->getId() !== $command->actorUserId) {
                    throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Application not found.');
                }

                $calendar = $this->calendarRepository->findOneByApplication($application);
                if ($calendar === null) {
                    throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Application has no calendar.');
                }

                $event->setCalendar($calendar);
            }

            $this->eventRepository->save($event);
        });

        $this->cacheInvalidationService->invalidateEventCaches($command->applicationSlug, $command->actorUserId);
    }
}
