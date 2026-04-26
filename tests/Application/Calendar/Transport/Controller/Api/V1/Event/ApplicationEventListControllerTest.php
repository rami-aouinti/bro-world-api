<?php

declare(strict_types=1);

namespace App\Tests\Application\Calendar\Transport\Controller\Api\V1\Event;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class ApplicationEventListControllerTest extends WebTestCase
{
    #[TestDox('GET /api/v1/calendar/events?applicationSlug={applicationSlug} does not expose private events.')]
    public function testPublicApplicationEventListDoesNotExposePrivateEvents(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', self::API_URL_PREFIX . '/v1/calendar/events?applicationSlug=crm-support-desk');
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

    #[TestDox('GET /api/v1/calendar/events/me?applicationSlug={applicationSlug} keeps owner/user access logic.')]
    public function testPrivateApplicationEventListStillReturnsOwnerEvents(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', self::API_URL_PREFIX . '/v1/calendar/events/me?applicationSlug=crm-support-desk');
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
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), 'Response:
' . $response);

        $responseData = JSON::decode($content, true);

        self::assertIsArray($responseData);
        self::assertLessThanOrEqual(3, count($responseData));

        if ($responseData !== []) {
            self::assertArrayHasKey('title', $responseData[0]);
            self::assertArrayHasKey('startAt', $responseData[0]);
        }
    }

    #[TestDox('GET listing endpoints keep business results after repository joins enrichment.')]
    public function testEventListingEndpointsKeepExpectedBusinessBehavior(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', self::API_URL_PREFIX . '/v1/calendar/private/events?limit=50');
        $privateResponse = $client->getResponse();
        $privateContent = $privateResponse->getContent();

        self::assertNotFalse($privateContent);
        self::assertSame(Response::HTTP_OK, $privateResponse->getStatusCode(), "Response:\n" . $privateResponse);

        $privateData = JSON::decode($privateContent, true);

        self::assertIsArray($privateData);
        self::assertArrayHasKey('items', $privateData);
        self::assertArrayHasKey('pagination', $privateData);
        self::assertGreaterThan(0, $privateData['pagination']['totalItems']);

        $privateTitles = array_map(
            static fn (array $item): string => (string)($item['title'] ?? ''),
            $privateData['items'],
        );
        self::assertContains('John Root standalone private event #1', $privateTitles);

        $client->request('GET', self::API_URL_PREFIX . '/v1/calendar/events/me?applicationSlug=crm-support-desk&limit=50');
        $applicationPrivateResponse = $client->getResponse();
        $applicationPrivateContent = $applicationPrivateResponse->getContent();

        self::assertNotFalse($applicationPrivateContent);
        self::assertSame(Response::HTTP_OK, $applicationPrivateResponse->getStatusCode(), "Response:\n" . $applicationPrivateResponse);

        $applicationPrivateData = JSON::decode($applicationPrivateContent, true);

        self::assertIsArray($applicationPrivateData);
        self::assertArrayHasKey('items', $applicationPrivateData);
        self::assertNotEmpty($applicationPrivateData['items']);

        $client->request('GET', self::API_URL_PREFIX . '/v1/calendar/events/upcoming?applicationSlug=crm-support-desk&limit=3');
        $upcomingResponse = $client->getResponse();
        $upcomingContent = $upcomingResponse->getContent();

        self::assertNotFalse($upcomingContent);
        self::assertSame(Response::HTTP_OK, $upcomingResponse->getStatusCode(), "Response:\n" . $upcomingResponse);

        $upcomingData = JSON::decode($upcomingContent, true);

        self::assertIsArray($upcomingData);
        self::assertLessThanOrEqual(3, count($upcomingData));

        if ($upcomingData !== []) {
            self::assertArrayHasKey('title', $upcomingData[0]);
            self::assertArrayHasKey('startAt', $upcomingData[0]);
        }
    }

    #[TestDox('GET /api/v1/calendar/events/owner is restricted to application owner and returns all application events.')]
    public function testOwnerApplicationEventListIsOwnerOnly(): void
    {
        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/calendar/events/owner?applicationSlug=crm-general-core&limit=100');

        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());
        $ownerContent = $ownerClient->getResponse()->getContent();
        self::assertNotFalse($ownerContent);
        $ownerPayload = JSON::decode($ownerContent, true);
        self::assertNotEmpty($ownerPayload['items']);

        $viewerClient = $this->getTestClient('john-user', 'password-user');
        $viewerClient->request('GET', self::API_URL_PREFIX . '/v1/calendar/events/owner?applicationSlug=crm-general-core');
        self::assertSame(Response::HTTP_FORBIDDEN, $viewerClient->getResponse()->getStatusCode());
    }

    #[TestDox('GET /api/v1/calendar/events/employee-assigned returns connected employee events and rejects non employees.')]
    public function testEmployeeAssignedEventListIsRestrictedToApplicationEmployees(): void
    {
        $employeeClient = $this->getTestClient('john-root', 'password-root');
        $employeeClient->request('GET', self::API_URL_PREFIX . '/v1/calendar/events/employee-assigned?applicationSlug=crm-general-core&limit=100');

        self::assertSame(Response::HTTP_OK, $employeeClient->getResponse()->getStatusCode());
        $content = $employeeClient->getResponse()->getContent();
        self::assertNotFalse($content);
        $payload = JSON::decode($content, true);

        $titles = array_map(
            static fn (array $item): string => (string)($item['title'] ?? ''),
            $payload['items'] ?? [],
        );
        self::assertContains('CRM General - John Root assigned planning', $titles);

        $nonEmployeeClient = $this->getTestClient('alice', 'password-alice');
        $nonEmployeeClient->request('GET', self::API_URL_PREFIX . '/v1/calendar/events/employee-assigned?applicationSlug=crm-general-core');
        self::assertSame(Response::HTTP_FORBIDDEN, $nonEmployeeClient->getResponse()->getStatusCode());
    }
}
