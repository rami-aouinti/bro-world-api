<?php

declare(strict_types=1);

namespace App\Tests\Unit\Calendar\Application\MessageHandler;

use App\Calendar\Application\Message\PatchEventCommand;
use App\Calendar\Application\MessageHandler\PatchEventCommandHandler;
use App\Calendar\Domain\Entity\Event;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PatchEventCommandHandlerTest extends TestCase
{
    public function testInvokeRejectsInvalidPartialPatchWithOnlyEndAt(): void
    {
        $event = $this->createOwnedEvent(
            startAt: new DateTimeImmutable('2030-01-01T10:00:00+00:00'),
            endAt: new DateTimeImmutable('2030-01-01T11:00:00+00:00'),
        );

        [$handler] = $this->createHandlerWithEvent($event);

        $command = $this->createPatchCommand(
            actorUserId: $event->getUser()?->getId() ?? '',
            eventId: $event->getId(),
            startAt: null,
            endAt: new DateTimeImmutable('2030-01-01T09:00:00+00:00'),
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        $this->expectExceptionMessage('Field "endAt" must be greater than or equal to "startAt".');

        $handler($command);
    }

    public function testInvokeRejectsInvalidPartialPatchWithOnlyStartAt(): void
    {
        $event = $this->createOwnedEvent(
            startAt: new DateTimeImmutable('2030-01-01T10:00:00+00:00'),
            endAt: new DateTimeImmutable('2030-01-01T11:00:00+00:00'),
        );

        [$handler] = $this->createHandlerWithEvent($event);

        $command = $this->createPatchCommand(
            actorUserId: $event->getUser()?->getId() ?? '',
            eventId: $event->getId(),
            startAt: new DateTimeImmutable('2030-01-01T12:00:00+00:00'),
            endAt: null,
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        $this->expectExceptionMessage('Field "endAt" must be greater than or equal to "startAt".');

        $handler($command);
    }

    public function testInvokeAcceptsValidPartialPatch(): void
    {
        $event = $this->createOwnedEvent(
            startAt: new DateTimeImmutable('2030-01-01T10:00:00+00:00'),
            endAt: new DateTimeImmutable('2030-01-01T11:00:00+00:00'),
        );

        [$handler, $eventRepository, $cacheInvalidationService] = $this->createHandlerWithEvent($event);

        $command = $this->createPatchCommand(
            actorUserId: $event->getUser()?->getId() ?? '',
            eventId: $event->getId(),
            startAt: null,
            endAt: new DateTimeImmutable('2030-01-01T12:00:00+00:00'),
        );

        $eventRepository->expects(self::once())->method('save')->with($event);
        $cacheInvalidationService->expects(self::once())->method('invalidateEventCaches')->with(null, $command->actorUserId);

        $handler($command);

        self::assertSame('2030-01-01T10:00:00+00:00', $event->getStartAt()->format(DATE_ATOM));
        self::assertSame('2030-01-01T12:00:00+00:00', $event->getEndAt()->format(DATE_ATOM));
    }

    private function createOwnedEvent(DateTimeImmutable $startAt, DateTimeImmutable $endAt): Event
    {
        $user = new User();
        $event = new Event();
        $event->setUser($user);
        $event->setStartAt($startAt);
        $event->setEndAt($endAt);

        return $event;
    }

    /**
     * @return array{PatchEventCommandHandler, EventRepository, CacheInvalidationService}
     */
    private function createHandlerWithEvent(Event $event): array
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->method('getEntityManager')->willReturn($entityManager);
        $eventRepository->method('find')->willReturn($event);

        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);

        $handler = new PatchEventCommandHandler(
            eventRepository: $eventRepository,
            applicationRepository: $this->createMock(ApplicationRepository::class),
            cacheInvalidationService: $cacheInvalidationService,
        );

        return [$handler, $eventRepository, $cacheInvalidationService];
    }

    private function createPatchCommand(
        string $actorUserId,
        string $eventId,
        ?DateTimeImmutable $startAt,
        ?DateTimeImmutable $endAt,
    ): PatchEventCommand {
        return new PatchEventCommand(
            operationId: 'op-id',
            actorUserId: $actorUserId,
            eventId: $eventId,
            title: null,
            description: null,
            startAt: $startAt,
            endAt: $endAt,
            visibility: null,
            location: null,
            isAllDay: null,
            timezone: null,
            url: null,
            color: null,
            backgroundColor: null,
            borderColor: null,
            textColor: null,
            organizerName: null,
            organizerEmail: null,
            attendees: null,
            rrule: null,
            recurrenceExceptions: null,
            recurrenceEndAt: null,
            recurrenceCount: null,
            reminders: null,
            metadata: null,
            applicationSlug: null,
        );
    }
}
