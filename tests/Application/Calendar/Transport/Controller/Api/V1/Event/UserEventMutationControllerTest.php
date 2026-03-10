<?php

declare(strict_types=1);

namespace App\Tests\Application\Calendar\Transport\Controller\Api\V1\Event;

use App\Calendar\Infrastructure\Repository\EventRepository;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
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
}
