<?php

declare(strict_types=1);

namespace App\Tests\Application\Calendar\Transport\Controller\Api\V1\Event;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class ApplicationEventListControllerTest extends WebTestCase
{
    #[TestDox('GET /api/v1/calendar/applications/{applicationSlug}/events does not expose private events.')]
    public function testPublicApplicationEventListDoesNotExposePrivateEvents(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', self::API_URL_PREFIX . '/v1/calendar/applications/crm-support-desk/events');
        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);

        self::assertIsArray($responseData);
        self::assertArrayHasKey('items', $responseData);
        self::assertArrayHasKey('pagination', $responseData);
        self::assertCount(0, $responseData['items']);
        self::assertSame(0, $responseData['pagination']['totalItems']);
    }

    #[TestDox('GET /api/v1/calendar/applications/{applicationSlug}/events/me keeps owner/user access logic.')]
    public function testPrivateApplicationEventListStillReturnsOwnerEvents(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', self::API_URL_PREFIX . '/v1/calendar/applications/crm-support-desk/events/me');
        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);

        self::assertIsArray($responseData);
        self::assertArrayHasKey('items', $responseData);
        self::assertNotEmpty($responseData['items']);
    }

    #[TestDox('GET /api/v1/calendar/events/upcoming returns up to three nearest events for logged user.')]
    public function testUpcomingEventsEndpointReturnsNearestItems(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', self::API_URL_PREFIX . '/v1/calendar/events/upcoming');
        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:
" . $response);

        $responseData = JSON::decode($content, true);

        self::assertIsArray($responseData);
        self::assertLessThanOrEqual(3, count($responseData));

        if ($responseData !== []) {
            self::assertArrayHasKey('title', $responseData[0]);
            self::assertArrayHasKey('startAt', $responseData[0]);
        }
    }

}
