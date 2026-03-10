<?php

declare(strict_types=1);

namespace App\Tests\Application\Platform\Transport\Controller\Api\V1;

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
class PluginCreateControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/plugin';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `POST /v1/plugin` returns forbidden error for non-root user.')]
    public function testThatCreateActionForNonRootUserReturnsForbiddenResponse(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');

        $requestData = [
            'name' => 'New plugin',
            'description' => 'A plugin for tests',
            'enabled' => true,
            'private' => false,
            'pluginKey' => 'chat',
        ];

        $client->request(method: 'POST', uri: $this->baseUrl, content: JSON::encode($requestData));
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @param array<string, bool|string> $requestData
     *
     * @throws Throwable
     */
    #[DataProvider('dataProviderWithIncorrectData')]
    #[TestDox('Test that `POST /v1/plugin` with wrong data returns validation error.')]
    public function testThatCreateActionForRootUserWithWrongDataReturnsValidationErrorResponse(
        array $requestData,
        string $error
    ): void {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(method: 'POST', uri: $this->baseUrl, content: JSON::encode($requestData));
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
    #[TestDox('Test that `POST /v1/plugin` for the Root user returns success response and auto-generates photo.')]
    public function testThatCreateActionForRootUserReturnsSuccessResponse(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $requestData = [
            'name' => 'Plugin sans photo',
            'description' => 'Plugin created without photo payload',
            'enabled' => true,
            'private' => false,
            'pluginKey' => 'chat',
        ];

        $client->request(method: 'POST', uri: $this->baseUrl, content: JSON::encode($requestData));
        $response = $client->getResponse();
        $responseContent = $response->getContent();
        self::assertNotFalse($responseContent);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);
        $responseData = JSON::decode($responseContent, true);
        self::assertArrayHasKey('id', $responseData);
        self::assertArrayHasKey('name', $responseData);
        self::assertArrayHasKey('description', $responseData);
        self::assertArrayHasKey('enabled', $responseData);
        self::assertArrayHasKey('private', $responseData);
        self::assertArrayHasKey('photo', $responseData);
        self::assertArrayHasKey('pluginKey', $responseData);
        self::assertSame($requestData['name'], $responseData['name']);
        self::assertSame($requestData['description'], $responseData['description']);
        self::assertStringStartsWith('https://ui-avatars.com/api/?name=', $responseData['photo']);
        self::assertSame($requestData['pluginKey'], $responseData['pluginKey']);
    }

    /**
     * @return Generator<array{0: array<string, bool|string>, 1: string}>
     */
    public static function dataProviderWithIncorrectData(): Generator
    {
        yield [
            [
                'name' => '',
                'description' => 'Plugin description',
                'enabled' => true,
                'private' => false,
                'pluginKey' => 'chat',
            ],
            'This value should not be blank.',
        ];
        yield [
            [
                'name' => 'A',
                'description' => 'Plugin description',
                'enabled' => true,
                'private' => false,
                'pluginKey' => 'chat',
            ],
            'This value is too short.',
        ];
    }
}
