<?php

declare(strict_types=1);

namespace App\Tests\Application\Calendar\Transport\Controller\Api\V1\Event;

use App\Calendar\Application\Message\CreateEventCommand;
use App\Calendar\Application\Message\PatchEventCommand;
use App\Calendar\Application\MessageHandler\CreateEventCommandHandler;
use App\Calendar\Application\MessageHandler\PatchEventCommandHandler;
use App\Calendar\Domain\Entity\Event;
use App\Calendar\Domain\Enum\EventVisibility;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\HttpFoundation\Response;

final class UserEventMutationControllerTest extends WebTestCase
{
    #[TestDox('POST /api/v1/calendar/private/events returns 202 with operationId and does not write synchronously.')]
    public function testCreateEventReturnsAcceptedWithoutImmediateWrite(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $payload = [
            'title' => 'async-event-title',
            'description' => 'queued',
            'startAt' => '2030-01-01T10:00:00+00:00',
            'endAt' => '2030-01-01T11:00:00+00:00',
        ];

        $client->request('POST', self::API_URL_PREFIX . '/v1/calendar/private/events', content: JSON::encode($payload));
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode(), "Response:\n" . $response);

        $content = $response->getContent();
        self::assertNotFalse($content);
        $data = JSON::decode($content, true);

        self::assertArrayHasKey('operationId', $data);

        /** @var EventRepository $repository */
        $repository = static::getContainer()->get(EventRepository::class);
        self::assertCount(0, $repository->findBy([
            'title' => 'async-event-title',
        ]));
    }

    #[TestDox('POST /api/v1/calendar/private/events fails fast with 422 for invalid range.')]
    public function testCreateEventFailsFastOnInvalidDateRange(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $payload = [
            'title' => 'invalid-event-range',
            'startAt' => '2030-01-01T12:00:00+00:00',
            'endAt' => '2030-01-01T11:00:00+00:00',
        ];

        $client->request('POST', self::API_URL_PREFIX . '/v1/calendar/private/events', content: JSON::encode($payload));
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), "Response:\n" . $response);
    }

    #[TestDox('Create and patch payload accept advanced fields and persist them when queued messages are consumed.')]
    public function testCreateAndPatchAcceptAndPersistAdvancedFieldsFromQueuedMessages(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $createPayload = [
            'title' => 'queued-advanced-create',
            'description' => 'description-create',
            'startAt' => '2030-01-01T10:00:00+00:00',
            'endAt' => '2030-01-01T11:00:00+00:00',
            'status' => 'tentative',
            'visibility' => 'public',
            'location' => 'Paris',
            'isAllDay' => true,
            'timezone' => 'Europe/Paris',
            'url' => 'https://example.com/events/1',
            'color' => '#2563eb',
            'backgroundColor' => '#dbeafe',
            'borderColor' => '#1d4ed8',
            'textColor' => '#0f172a',
            'organizerName' => 'Jane Doe',
            'organizerEmail' => 'jane@example.com',
            'attendees' => [['email' => 'attendee@example.com']],
            'rrule' => 'FREQ=WEEKLY;COUNT=2',
            'recurrenceExceptions' => ['2030-01-08T10:00:00+00:00'],
            'recurrenceEndAt' => '2030-01-15T10:00:00+00:00',
            'recurrenceCount' => 2,
            'reminders' => [['method' => 'email', 'minutes' => 30]],
            'metadata' => ['source' => 'functional-test'],
        ];

        $client->request('POST', self::API_URL_PREFIX . '/v1/calendar/private/events', content: JSON::encode($createPayload));
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());

        $createEnvelopes = $transport->getSent();
        self::assertCount(1, $createEnvelopes);
        $createCommand = $createEnvelopes[0]->getMessage();
        self::assertInstanceOf(CreateEventCommand::class, $createCommand);

        /** @var CreateEventCommandHandler $createHandler */
        $createHandler = static::getContainer()->get(CreateEventCommandHandler::class);
        $createHandler($createCommand);

        /** @var EventRepository $eventRepository */
        $eventRepository = static::getContainer()->get(EventRepository::class);
        $createdEvent = $eventRepository->findOneBy(['title' => 'queued-advanced-create']);
        self::assertInstanceOf(Event::class, $createdEvent);
        self::assertSame(EventVisibility::PUBLIC, $createdEvent->getVisibility());
        self::assertTrue($createdEvent->isAllDay());
        self::assertSame('Europe/Paris', $createdEvent->getTimezone());
        self::assertSame('https://example.com/events/1', $createdEvent->getUrl());
        self::assertSame('Jane Doe', $createdEvent->getOrganizerName());
        self::assertSame(['source' => 'functional-test'], $createdEvent->getMetadata());

        $transport->reset();
        $patchPayload = [
            'title' => 'queued-advanced-patched',
            'description' => 'description-patched',
            'visibility' => 'private',
            'location' => 'Lyon',
            'isAllDay' => false,
            'timezone' => 'Europe/London',
            'url' => 'https://example.com/events/2',
            'color' => '#22c55e',
            'backgroundColor' => '#dcfce7',
            'borderColor' => '#16a34a',
            'textColor' => '#14532d',
            'organizerName' => 'John Smith',
            'organizerEmail' => 'john@example.com',
            'attendees' => [['email' => 'other@example.com']],
            'rrule' => 'FREQ=MONTHLY;COUNT=1',
            'recurrenceExceptions' => ['2030-02-01T10:00:00+00:00'],
            'recurrenceEndAt' => '2030-02-01T10:00:00+00:00',
            'recurrenceCount' => 1,
            'reminders' => [['method' => 'popup', 'minutes' => 15]],
            'metadata' => ['source' => 'functional-test-patch'],
        ];

        $client->request('PATCH', self::API_URL_PREFIX . '/v1/calendar/private/events/' . $createdEvent->getId(), content: JSON::encode($patchPayload));
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());

        $patchEnvelopes = $transport->getSent();
        self::assertCount(1, $patchEnvelopes);
        $patchCommand = $patchEnvelopes[0]->getMessage();
        self::assertInstanceOf(PatchEventCommand::class, $patchCommand);

        /** @var PatchEventCommandHandler $patchHandler */
        $patchHandler = static::getContainer()->get(PatchEventCommandHandler::class);
        $patchHandler($patchCommand);

        $patchedEvent = $eventRepository->find($createdEvent->getId());
        self::assertInstanceOf(Event::class, $patchedEvent);
        self::assertSame('queued-advanced-patched', $patchedEvent->getTitle());
        self::assertSame(EventVisibility::PRIVATE, $patchedEvent->getVisibility());
        self::assertFalse($patchedEvent->isAllDay());
        self::assertSame('Europe/London', $patchedEvent->getTimezone());
        self::assertSame('https://example.com/events/2', $patchedEvent->getUrl());
        self::assertSame('John Smith', $patchedEvent->getOrganizerName());
        self::assertSame([['email' => 'other@example.com']], $patchedEvent->getAttendees());
        self::assertSame('FREQ=MONTHLY;COUNT=1', $patchedEvent->getRrule());
        self::assertSame(['source' => 'functional-test-patch'], $patchedEvent->getMetadata());
    }
}
