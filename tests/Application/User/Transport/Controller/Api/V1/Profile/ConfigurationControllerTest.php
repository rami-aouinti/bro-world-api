<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\Profile;

use App\Configuration\Application\Resource\ConfigurationResource;
use App\Configuration\Domain\Entity\Configuration;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use App\User\Application\Resource\UserResource;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\DataFixtures\ORM\LoadUserGroupData;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @package App\Tests
 */
class ConfigurationControllerTest extends WebTestCase
{
    private const string CONFIGURATION_KEY = 'user.notifications.preferences';

    private string $baseUrl = self::API_URL_PREFIX . '/v1/profile/configuration/' . self::CONFIGURATION_KEY;

    /**
     * @throws Throwable
     */
    #[TestDox('Test that profile configuration endpoint returns configuration of logged in user.')]
    public function testThatGetProfileConfigurationReturnsSuccessResponse(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request(method: 'GET', uri: $this->baseUrl);
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);
        $responseData = JSON::decode($content, true);
        self::assertSame(self::CONFIGURATION_KEY, $responseData['configurationKey']);
        self::assertIsArray($responseData['configurationValue']);
        self::assertCount(6, $responseData['configurationValue']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that profile configuration endpoint patches logged in user configuration value.')]
    public function testThatPatchProfileConfigurationReturnsSuccessResponse(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');
        $payload = [
            'configurationValue' => [
                [
                    'switchState' => false,
                    'text' => 'Email me when someone follows me',
                ],
            ],
        ];

        $client->request(method: 'PATCH', uri: $this->baseUrl, content: JSON::encode($payload));
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);
        $responseData = JSON::decode($content, true);
        self::assertSame(self::CONFIGURATION_KEY, $responseData['configurationKey']);
        self::assertCount(1, $responseData['configurationValue']);
        self::assertFalse($responseData['configurationValue'][0]['switchState']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that profile configuration create endpoint creates a user scoped configuration.')]
    public function testThatPostProfileConfigurationReturnsCreatedResponse(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');
        $createPayload = [
            'configurationKey' => 'user.profile.test.preferences',
            'configurationValue' => [
                'theme' => 'dark',
                'compact' => true,
            ],
        ];

        $client->request(
            method: 'POST',
            uri: self::API_URL_PREFIX . '/v1/profile/configuration',
            content: JSON::encode($createPayload),
        );

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), 'Response:
' . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame($createPayload['configurationKey'], $responseData['configurationKey']);
        self::assertSame('user', $responseData['scope']);
        self::assertSame($createPayload['configurationValue']['theme'], $responseData['configurationValue']['theme']);

        $userResource = static::getContainer()->get(UserResource::class);
        $configurationResource = static::getContainer()->get(ConfigurationResource::class);
        $user = $userResource->findOneBy([
            'username' => 'john-user',
        ]);
        self::assertInstanceOf(User::class, $user);

        $createdConfiguration = $configurationResource->findOneBy([
            'configurationKey' => $createPayload['configurationKey'],
            'user' => $user,
        ]);
        self::assertInstanceOf(Configuration::class, $createdConfiguration);
        self::assertSame('user', $createdConfiguration->getScopeValue());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that user creation creates default private user notification preferences configuration.')]
    public function testThatCreatingUserCreatesDefaultNotificationConfiguration(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $requestData = [
            'username' => 'test-config-user',
            'firstName' => 'Config',
            'lastName' => 'User',
            'email' => 'test-config-user@test.com',
            'userGroups' => [
                LoadUserGroupData::getUuidByKey('Role-logged'),
            ],
            'password' => 'test12345',
            'language' => 'en',
            'locale' => 'en',
            'timezone' => 'UTC',
        ];

        $client->request(method: 'POST', uri: self::API_URL_PREFIX . '/v1/user', content: JSON::encode($requestData));
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $userResource = static::getContainer()->get(UserResource::class);
        $configurationResource = static::getContainer()->get(ConfigurationResource::class);
        $user = $userResource->findOneBy([
            'username' => 'test-config-user',
        ]);
        self::assertInstanceOf(User::class, $user);
        $configuration = $configurationResource->findOneBy([
            'configurationKey' => self::CONFIGURATION_KEY,
            'user' => $user,
            'private' => true,
        ]);

        self::assertInstanceOf(Configuration::class, $configuration);
        self::assertCount(6, $configuration->getConfigurationValue());
    }
}
