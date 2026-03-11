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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class UserEventMutationControllerTest extends WebTestCase
{
    private const EVENT_ID = '550e8400-e29b-41d4-a716-446655440000';

    #[TestDox('Mutation routes return 202 and operationId for private user and application owner flows.')]
    #[DataProvider('provideMutationRoutes')]
    public function testMutationRoutesReturnAcceptedAndOperationId(
        string $username,
        string $password,
        string $method,
        string $path,
        ?array $payload,
    ): void {
        $client = $this->getTestClient($username, $password);

        $client->request($method, self::API_URL_PREFIX . $path, content: null === $payload ? null : JSON::encode($payload));
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode(), "Response:\n" . $response);

        $content = $response->getContent();
        self::assertNotFalse($content);
        $data = JSON::decode($content, true);

        self::assertIsArray($data);
        self::assertArrayHasKey('operationId', $data);
        self::assertIsString($data['operationId']);
        self::assertNotSame('', $data['operationId']);
    }

    /**
     * @return iterable<string, array{string, string, string, string, ?array<string, mixed>}>
     */
    public static function provideMutationRoutes(): iterable
    {
        yield 'private_create' => [
            'john-user',
            'password-user',
            'POST',
            '/v1/calendar/private/events',
            [
                'title' => 'private-create',
                'startAt' => '2030-01-01T10:00:00+00:00',
                'endAt' => '2030-01-01T11:00:00+00:00',
            ],
        ];

        yield 'private_patch' => [
            'john-user',
            'password-user',
            'PATCH',
            '/v1/calendar/private/events/' . self::EVENT_ID,
            [
                'title' => 'private-patch',
            ],
        ];

        yield 'private_delete' => [
            'john-user',
            'password-user',
            'DELETE',
            '/v1/calendar/private/events/' . self::EVENT_ID,
            null,
        ];

        yield 'private_cancel' => [
            'john-user',
            'password-user',
            'POST',
            '/v1/calendar/private/events/' . self::EVENT_ID . '/cancel',
            null,
        ];

        yield 'application_create' => [
            'john-root',
            'password-root',
            'POST',
            '/v1/calendar/private/applications/crm-support-desk/events',
            [
                'title' => 'application-create',
                'startAt' => '2030-01-01T10:00:00+00:00',
                'endAt' => '2030-01-01T11:00:00+00:00',
            ],
        ];

        yield 'application_patch' => [
            'john-root',
            'password-root',
            'PATCH',
            '/v1/calendar/private/applications/crm-support-desk/events/' . self::EVENT_ID,
            [
                'title' => 'application-patch',
            ],
        ];

        yield 'application_delete' => [
            'john-root',
            'password-root',
            'DELETE',
            '/v1/calendar/private/applications/crm-support-desk/events/' . self::EVENT_ID,
            null,
        ];

        yield 'application_cancel' => [
            'john-root',
            'password-root',
            'POST',
            '/v1/calendar/private/applications/crm-support-desk/events/' . self::EVENT_ID . '/cancel',
            null,
        ];
    }

    #[TestDox('OpenAPI operationIds are aligned for create/patch/delete/cancel in private and application routes.')]
    public function testOpenApiOperationIdsForMutationRoutes(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', '/api/doc.json');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = JSON::decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);

        $paths = $payload['paths'] ?? [];
        self::assertIsArray($paths);

        $expectedOperationIds = [
            '/v1/calendar/private/events' => ['post' => 'calendar_private_event_create'],
            '/v1/calendar/private/events/{eventId}' => [
                'patch' => 'calendar_private_event_patch',
                'delete' => 'calendar_private_event_delete',
            ],
            '/v1/calendar/private/events/{eventId}/cancel' => ['post' => 'calendar_private_event_cancel'],
            '/v1/calendar/private/applications/{applicationSlug}/events' => ['post' => 'calendar_application_event_create'],
            '/v1/calendar/private/applications/{applicationSlug}/events/{eventId}' => [
                'patch' => 'calendar_application_event_patch',
                'delete' => 'calendar_application_event_delete',
            ],
            '/v1/calendar/private/applications/{applicationSlug}/events/{eventId}/cancel' => ['post' => 'calendar_application_event_cancel'],
        ];

        foreach ($expectedOperationIds as $path => $operations) {
            self::assertArrayHasKey($path, $paths, 'Missing path in documentation: ' . $path);

            foreach ($operations as $method => $operationId) {
                self::assertArrayHasKey($method, $paths[$path], 'Missing method in documentation: ' . strtoupper($method) . ' ' . $path);
                self::assertSame($operationId, $paths[$path][$method]['operationId'] ?? null, 'Unexpected operationId for ' . strtoupper($method) . ' ' . $path);
            }
        }
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
