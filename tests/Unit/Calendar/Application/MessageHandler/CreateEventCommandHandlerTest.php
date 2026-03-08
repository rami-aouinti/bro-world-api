<?php

declare(strict_types=1);

namespace App\Tests\Unit\Calendar\Application\MessageHandler;

use App\Calendar\Application\Message\CreateEventCommand;
use App\Calendar\Application\MessageHandler\CreateEventCommandHandler;
use App\Calendar\Domain\Enum\EventStatus;
use App\Calendar\Infrastructure\Repository\CalendarRepository;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class CreateEventCommandHandlerTest extends TestCase
{
    public function testInvokePersistsEventInsideTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->method('getEntityManager')->willReturn($entityManager);
        $eventRepository->expects(self::once())->method('save');

        $user = new User();
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn($user);

        $handler = new CreateEventCommandHandler(
            eventRepository: $eventRepository,
            userRepository: $userRepository,
            applicationRepository: $this->createMock(ApplicationRepository::class),
            calendarRepository: $this->createMock(CalendarRepository::class),
        );

        $handler(new CreateEventCommand(
            operationId: 'op-id',
            actorUserId: 'user-id',
            title: 'title',
            description: 'description',
            startAt: new DateTimeImmutable('2030-01-01T10:00:00+00:00'),
            endAt: new DateTimeImmutable('2030-01-01T11:00:00+00:00'),
            status: EventStatus::CONFIRMED,
            location: null,
        ));
    }
}
