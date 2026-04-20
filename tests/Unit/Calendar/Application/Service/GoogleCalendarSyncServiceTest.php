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
                        && !isset($query['timeMax']);
                }),
            )
            ->willReturn($response);

        $service = new GoogleCalendarSyncService($eventRepository, $httpClient);

        $user = $this->createMock(User::class);
        $service->syncBidirectional($user, 'token');
    }
}
