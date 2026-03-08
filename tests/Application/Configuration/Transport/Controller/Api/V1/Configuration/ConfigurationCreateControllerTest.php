<?php

declare(strict_types=1);

namespace App\Tests\Application\Configuration\Transport\Controller\Api\V1\Configuration;

use App\Configuration\Infrastructure\Repository\ConfigurationRepository;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ConfigurationCreateControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/configuration';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `POST /api/v1/configuration` returns forbidden for non-root user.')]
    public function testThatCreateActionForNonRootUserReturnsForbiddenResponse(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');

        $client->request(method: 'POST', uri: $this->baseUrl, content: JSON::encode($this->getValidPayload()));
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @param array<string, mixed> $requestData
     *
     * @throws Throwable
     */
    #[DataProvider('dataProviderWithIncorrectData')]
    #[TestDox('Test that `POST /api/v1/configuration` with wrong data returns validation error.')]
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
    #[TestDox('Test that `POST /api/v1/configuration` for root returns 202 and does not write synchronously.')]
    public function testThatCreateActionForRootUserReturnsAcceptedResponse(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $requestData = $this->getValidPayload();
        $requestData['configurationKey'] = 'system.private.created.async';

        $client->request(method: 'POST', uri: $this->baseUrl, content: JSON::encode($requestData));
        $response = $client->getResponse();
        $responseContent = $response->getContent();
        self::assertNotFalse($responseContent);
        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode(), "Response:\n" . $response);
        $responseData = JSON::decode($responseContent, true);
        self::assertArrayHasKey('operationId', $responseData);

        /** @var ConfigurationRepository $repository */
        $repository = static::getContainer()->get(ConfigurationRepository::class);
        self::assertNull($repository->findOneBy(['configurationKey' => $requestData['configurationKey']]));
    }

    /**
     * @return Generator<array{0: array<string, mixed>, 1: string}>
     */
    public static function dataProviderWithIncorrectData(): Generator
    {
        yield [[
            'configurationKey' => '',
            'configurationValue' => ['enabled' => true],
            'scope' => 'system',
            'private' => false,
        ], 'This value should not be blank.'];

        yield [[
            'configurationKey' => 'test.invalid.scope',
            'configurationValue' => ['enabled' => true],
            'scope' => 'invalid',
            'private' => false,
        ], 'The value you selected is not a valid choice.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function getValidPayload(): array
    {
        return [
            'configurationKey' => 'system.feature.flags',
            'configurationValue' => ['featureX' => true, 'featureY' => false],
            'scope' => 'system',
            'private' => false,
        ];
    }
}
