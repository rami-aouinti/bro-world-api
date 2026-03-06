<?php

declare(strict_types=1);

namespace App\Tests\Application\Platform\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @package App\Tests
 */
class PluginListControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/plugin';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/plugin` returns forbidden error for non-root user.')]
    public function testThatFindActionForNonRootUserReturnsForbiddenResponse(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/plugin` for the Root user returns success response.')]
    public function testThatFindActionForRootUserReturnsSuccessResponse(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(method: 'GET', uri: $this->baseUrl);
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);
        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertGreaterThanOrEqual(5, count($responseData));
        self::assertIsArray($responseData[0]);
        self::assertArrayHasKey('id', $responseData[0]);
        self::assertArrayHasKey('name', $responseData[0]);
        self::assertArrayHasKey('description', $responseData[0]);
        self::assertArrayHasKey('enabled', $responseData[0]);
        self::assertArrayHasKey('private', $responseData[0]);
        self::assertArrayHasKey('photo', $responseData[0]);
        self::assertArrayHasKey('pluginKey', $responseData[0]);
    }
}
