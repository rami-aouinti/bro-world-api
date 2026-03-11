<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1;

use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

use function json_encode;

class RecruitRootCrudControllersTest extends WebTestCase
{
    private const string UUID_V1 = 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6';

    #[TestDox('Every dedicated recruit root CRUD endpoint requires authentication.')]
    #[DataProvider('provideAllEndpoints')]
    public function testThatEveryEndpointRequiresAuthentication(string $method, string $path, ?array $payload = null): void
    {
        $client = $this->getTestClient();
        $client->request($method, self::API_URL_PREFIX . $path, server: ['CONTENT_TYPE' => 'application/json'], content: $payload !== null ? (string) json_encode($payload) : null);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), 'Failed endpoint: ' . $method . ' ' . $path);
    }

    #[TestDox('Every dedicated recruit root CRUD endpoint is routable for ROLE_ROOT.')]
    #[DataProvider('provideAllEndpoints')]
    public function testThatEveryEndpointIsRoutableForRoot(string $method, string $path, ?array $payload = null): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request($method, self::API_URL_PREFIX . $path, server: ['CONTENT_TYPE' => 'application/json'], content: $payload !== null ? (string) json_encode($payload) : null);

        self::assertNotSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode(), 'Route not found for: ' . $method . ' ' . $path);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>|null}>
     */
    public static function provideAllEndpoints(): iterable
    {
        foreach (['job', 'tag', 'company', 'salary'] as $resource) {
            yield $resource . '_list' => ['GET', '/v1/recruit/' . $resource, null];
            yield $resource . '_view' => ['GET', '/v1/recruit/' . $resource . '/' . self::UUID_V1, null];
            yield $resource . '_count' => ['GET', '/v1/recruit/' . $resource . '/count', null];
            yield $resource . '_ids' => ['GET', '/v1/recruit/' . $resource . '/ids', null];
            yield $resource . '_create' => ['POST', '/v1/recruit/' . $resource, []];
            yield $resource . '_patch' => ['PATCH', '/v1/recruit/' . $resource . '/' . self::UUID_V1, []];
            yield $resource . '_update' => ['PUT', '/v1/recruit/' . $resource . '/' . self::UUID_V1, []];
            yield $resource . '_delete' => ['DELETE', '/v1/recruit/' . $resource . '/' . self::UUID_V1, null];
        }
    }
}
