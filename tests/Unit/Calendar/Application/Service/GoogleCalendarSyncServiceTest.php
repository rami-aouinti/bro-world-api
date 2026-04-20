<?php

declare(strict_types=1);

namespace App\Tests\Unit\Calendar\Application\Service;

use App\Calendar\Application\Service\GoogleCalendarSyncService;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GoogleCalendarSyncServiceTest extends TestCase
{
    public function testSyncBidirectionalUsesCurrentYearWindowWhenNoTimeRangeIsProvided(): void
    {
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())
            ->method('findByUser')
            ->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getStatusCode')->willReturn(200);
        $response->expects(self::once())->method('toArray')->with(false)->willReturn(['items' => []]);

        $currentYear = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'https://www.googleapis.com/calendar/v3/calendars/primary/events',
                self::callback(static function (array $options) use ($currentYear): bool {
                    $query = $options['query'] ?? [];

                    return ($query['timeMin'] ?? null) === sprintf('%s-01-01T00:00:00+00:00', $currentYear)
                        && ($query['orderBy'] ?? null) === 'startTime'
                        && !isset($query['timeMax']);
                }),
            )
            ->willReturn($response);

        $service = new GoogleCalendarSyncService($eventRepository, $httpClient);

        $user = $this->createMock(User::class);
        $service->syncBidirectional($user, 'token');
    }

    public function testSyncBidirectionalPullsAllGooglePagesIncludingFutureEvents(): void
    {
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())
            ->method('findByUser')
            ->willReturn([]);

        $firstPageResponse = $this->createMock(ResponseInterface::class);
        $firstPageResponse->expects(self::once())->method('getStatusCode')->willReturn(200);
        $firstPageResponse->expects(self::once())->method('toArray')->with(false)->willReturn([
            'items' => [],
            'nextPageToken' => 'next-page',
        ]);

        $secondPageResponse = $this->createMock(ResponseInterface::class);
        $secondPageResponse->expects(self::once())->method('getStatusCode')->willReturn(200);
        $secondPageResponse->expects(self::once())->method('toArray')->with(false)->willReturn([
            'items' => [],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('request')
            ->withConsecutive(
                [
                    'GET',
                    'https://www.googleapis.com/calendar/v3/calendars/primary/events',
                    self::callback(static function (array $options): bool {
                        $query = $options['query'] ?? [];

                        return !isset($query['pageToken']);
                    }),
                ],
                [
                    'GET',
                    'https://www.googleapis.com/calendar/v3/calendars/primary/events',
                    self::callback(static function (array $options): bool {
                        $query = $options['query'] ?? [];

                        return ($query['pageToken'] ?? null) === 'next-page';
                    }),
                ],
            )
            ->willReturnOnConsecutiveCalls($firstPageResponse, $secondPageResponse);

        $service = new GoogleCalendarSyncService($eventRepository, $httpClient);

        $user = $this->createMock(User::class);
        $service->syncBidirectional($user, 'token');
    }

    public function testSyncBidirectionalCanSyncAllCalendarsIncludingHolidays(): void
    {
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())
            ->method('findByUser')
            ->willReturn([]);

        $calendarListResponse = $this->createMock(ResponseInterface::class);
        $calendarListResponse->expects(self::once())->method('getStatusCode')->willReturn(200);
        $calendarListResponse->expects(self::once())->method('toArray')->with(false)->willReturn([
            'items' => [
                ['id' => 'primary', 'summary' => 'Primary'],
                ['id' => 'en.fr#holiday@group.v.calendar.google.com', 'summary' => 'Holidays'],
            ],
        ]);

        $primaryEventsResponse = $this->createMock(ResponseInterface::class);
        $primaryEventsResponse->expects(self::once())->method('getStatusCode')->willReturn(200);
        $primaryEventsResponse->expects(self::once())->method('toArray')->with(false)->willReturn(['items' => []]);

        $holidayEventsResponse = $this->createMock(ResponseInterface::class);
        $holidayEventsResponse->expects(self::once())->method('getStatusCode')->willReturn(200);
        $holidayEventsResponse->expects(self::once())->method('toArray')->with(false)->willReturn(['items' => []]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(3))
            ->method('request')
            ->withConsecutive(
                [
                    'GET',
                    'https://www.googleapis.com/calendar/v3/users/me/calendarList',
                    self::anything(),
                ],
                [
                    'GET',
                    'https://www.googleapis.com/calendar/v3/calendars/primary/events',
                    self::anything(),
                ],
                [
                    'GET',
                    'https://www.googleapis.com/calendar/v3/calendars/en.fr%23holiday%40group.v.calendar.google.com/events',
                    self::anything(),
                ],
            )
            ->willReturnOnConsecutiveCalls($calendarListResponse, $primaryEventsResponse, $holidayEventsResponse);

        $service = new GoogleCalendarSyncService($eventRepository, $httpClient);

        $user = $this->createMock(User::class);
        $result = $service->syncBidirectional(
            $user,
            'token',
            includeAllCalendars: true,
            includeHolidays: true,
        );

        self::assertSame([
            'primary',
            'en.fr#holiday@group.v.calendar.google.com',
        ], $result['syncedCalendars']);
    }
}
