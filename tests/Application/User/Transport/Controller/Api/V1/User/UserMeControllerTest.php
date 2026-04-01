<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\User;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class UserMeControllerTest extends WebTestCase
{
    #[TestDox('GET /api/v1/users/me/sessions returns at least one session item for authenticated user.')]
    public function testSessionsAlwaysReturnsAtLeastOneItem(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', self::API_URL_PREFIX . '/v1/users/me/sessions');
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode((string)$response->getContent(), true);

        self::assertIsArray($payload);
        self::assertNotEmpty($payload);
        self::assertArrayHasKey('title', $payload[0]);
        self::assertArrayHasKey('badge', $payload[0]);
    }

    #[TestDox('GET /api/v1/users/me/applications/latest returns at most three items.')]
    public function testLatestApplicationsEndpointReturnsThreeItemsMax(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', self::API_URL_PREFIX . '/v1/users/me/applications/latest');
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode((string)$response->getContent(), true);

        self::assertIsArray($payload);
        self::assertLessThanOrEqual(3, count($payload));

        if ($payload !== []) {
            self::assertArrayHasKey('slug', $payload[0]);
            self::assertArrayHasKey('createdAt', $payload[0]);
        }
    }


    #[TestDox('GET /api/v1/users/me includes coins in payload.')]
    public function testMeIncludesCoins(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', self::API_URL_PREFIX . '/v1/users/me');
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode((string)$response->getContent(), true);

        self::assertArrayHasKey('coins', $payload);
        self::assertSame(5000, $payload['coins']);
    }
}
