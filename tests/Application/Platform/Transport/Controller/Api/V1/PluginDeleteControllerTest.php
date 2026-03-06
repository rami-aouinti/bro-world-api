<?php

declare(strict_types=1);

namespace App\Tests\Application\Platform\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Platform\Application\Resource\PluginResource;
use App\Platform\Domain\Entity\Plugin;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @package App\Tests
 */
class PluginDeleteControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/plugin';
    private PluginResource $pluginResource;
    private Plugin $plugin;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginResource = static::getContainer()->get(PluginResource::class);
        $plugin = $this->pluginResource->findOneBy(
            criteria: [
                'name' => 'Private Beta Plugin',
            ],
            throwExceptionIfNotFound: true,
        );
        self::assertInstanceOf(Plugin::class, $plugin);
        $this->plugin = $plugin;
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `DELETE /v1/plugin/{id}` returns forbidden error for non-root user.')]
    public function testThatDeleteActionForNonRootUserReturnsForbiddenResponse(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');

        $client->request(method: 'DELETE', uri: $this->baseUrl . '/' . $this->plugin->getId());
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), "Response:\n" . $response);

        /** @var Plugin|null $plugin */
        $plugin = $this->pluginResource->findOne($this->plugin->getId());
        self::assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `DELETE /v1/plugin/{id}` for the Root user returns success response.')]
    public function testThatDeleteActionForRootUserReturnsSuccessResponse(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('DELETE', $this->baseUrl . '/' . $this->plugin->getId());
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);
        $responseData = JSON::decode($content, true);
        self::assertArrayHasKey('id', $responseData);
        self::assertArrayHasKey('name', $responseData);
        self::assertArrayHasKey('description', $responseData);
        self::assertSame($this->plugin->getId(), $responseData['id']);

        /** @var Plugin|null $plugin */
        $plugin = $this->pluginResource->findOne($this->plugin->getId());
        self::assertNull($plugin);
    }
}
