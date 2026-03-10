<?php

declare(strict_types=1);

namespace App\Tests\Application\Tool\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @package App\Tests
 */
class StatisticsControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/statistics';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /api/v1/statistics` request returns `401` for non-logged user.')]
    public function testThatGetStatisticsReturns401ForAnonymousUser(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @throws Throwable
     */
    #[DataProvider('dataProviderAuthorizedUsers')]
    #[TestDox('Test that `GET /api/v1/statistics` returns `$responseCode` with login: `$login`.')]
    public function testThatGetStatisticsAccessControlWorks(string $login, string $password, int $responseCode): void
    {
        $client = $this->getTestClient($login, $password);

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();
        self::assertSame($responseCode, $response->getStatusCode(), "Response:\n" . $response);

        if ($responseCode !== Response::HTTP_OK) {
            return;
        }

        $content = $response->getContent();
        self::assertNotFalse($content);

        $data = JSON::decode($content, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('users', $data);
        self::assertArrayHasKey('applications', $data);
        self::assertArrayHasKey('plugins', $data);
        self::assertArrayHasKey('posts', $data);
    }

    /**
     * @return Generator<array{0: string, 1: string, 2: int}>
     */
    public static function dataProviderAuthorizedUsers(): Generator
    {
        yield ['john', 'password', Response::HTTP_FORBIDDEN];
        yield ['john-logged', 'password-logged', Response::HTTP_FORBIDDEN];
        yield ['john-api', 'password-api', Response::HTTP_FORBIDDEN];
        yield ['john-user', 'password-user', Response::HTTP_FORBIDDEN];
        yield ['john-admin', 'password-admin', Response::HTTP_OK];
        yield ['john-root', 'password-root', Response::HTTP_OK];
    }
}
