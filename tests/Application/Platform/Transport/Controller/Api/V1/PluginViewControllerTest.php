<?php

declare(strict_types=1);

namespace App\Tests\Application\Platform\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Platform\Infrastructure\DataFixtures\ORM\LoadPluginData;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @package App\Tests
 */
class PluginViewControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/plugin';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/plugin/{id}` returns forbidden error for non-root user.')]
    public function testThatFindOneActionForNonRootUserReturnsForbiddenResponse(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');

        $client->request('GET', $this->baseUrl . '/' . LoadPluginData::getUuidByKey('CRM Assistant'));
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/plugin/{id}` for the Root user returns success response.')]
    public function testThatFindOneActionForRootUserReturnsSuccessResponse(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', $this->baseUrl . '/' . LoadPluginData::getUuidByKey('CRM Assistant'));
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);
        $responseData = JSON::decode($content, true);
        self::assertArrayHasKey('id', $responseData);
        self::assertArrayHasKey('name', $responseData);
        self::assertArrayHasKey('description', $responseData);
        self::assertArrayHasKey('photo', $responseData);
        self::assertArrayHasKey('pluginKey', $responseData);
        self::assertSame(LoadPluginData::getUuidByKey('CRM Assistant'), $responseData['id']);
        self::assertStringStartsWith('https://ui-avatars.com/api/?name=', $responseData['photo']);
        self::assertContains($responseData['pluginKey'], ['calendar', 'chat', 'blog', 'language']);
    }
}
