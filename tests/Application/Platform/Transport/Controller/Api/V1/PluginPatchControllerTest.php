<?php

declare(strict_types=1);

namespace App\Tests\Application\Platform\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Platform\Application\Resource\PluginResource;
use App\Platform\Domain\Entity\Plugin;
use App\Platform\Infrastructure\DataFixtures\ORM\LoadPluginData;
use App\Tests\TestCase\WebTestCase;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @package App\Tests
 */
class PluginPatchControllerTest extends WebTestCase
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
        $plugin = $this->pluginResource->findOne(LoadPluginData::getUuidByKey('Knowledge Base Connector'));
        self::assertInstanceOf(Plugin::class, $plugin);
        $this->plugin = $plugin;
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `PATCH /v1/plugin/{id}` returns forbidden error for non-root user.')]
    public function testThatPatchActionForNonRootUserReturnsForbiddenResponse(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');

        $requestData = [
            'name' => 'Patched plugin name',
        ];

        $client->request(
            method: 'PATCH',
            uri: $this->baseUrl . '/' . $this->plugin->getId(),
            content: JSON::encode($requestData)
        );
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @param array<string, string> $requestData
     *
     * @throws Throwable
     */
    #[DataProvider('dataProviderWithIncorrectData')]
    #[TestDox('Test that `PATCH /v1/plugin/{id}` with wrong data returns validation error.')]
    public function testThatPatchActionForRootUserWithWrongDataReturnsValidationErrorResponse(
        array $requestData,
        string $error
    ): void {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(
            method: 'PATCH',
            uri: $this->baseUrl . '/' . $this->plugin->getId(),
            content: JSON::encode($requestData)
        );
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), "Response:\n" . $response);
        $responseData = JSON::decode($content, true);
        self::assertArrayHasKey('message', $responseData);
        self::assertStringContainsString($error, $responseData['message']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `PATCH /v1/plugin/{id}` for the Root user returns success response.')]
    public function testThatPatchActionForRootUserReturnsSuccessResponse(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $requestData = [
            'description' => 'Patched description for knowledge base connector',
            'enabled' => false,
        ];

        $client->request(
            method: 'PATCH',
            uri: $this->baseUrl . '/' . $this->plugin->getId(),
            content: JSON::encode($requestData)
        );
        $response = $client->getResponse();
        $responseContent = $response->getContent();
        self::assertNotFalse($responseContent);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);
        $responseData = JSON::decode($responseContent, true);
        self::assertArrayHasKey('id', $responseData);
        self::assertArrayHasKey('description', $responseData);
        self::assertArrayHasKey('enabled', $responseData);
        self::assertSame($requestData['description'], $responseData['description']);
        self::assertSame($requestData['enabled'], $responseData['enabled']);
    }

    /**
     * @return Generator<array{0: array<string, string>, 1: string}>
     */
    public static function dataProviderWithIncorrectData(): Generator
    {
        yield [
            [
                'name' => '',
            ],
            'This value should not be blank.',
        ];
    }
}
