<?php

declare(strict_types=1);

namespace App\Tests\Application\Platform\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PublicGeneralApplicationCatalogControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/application/public/general';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/application/public/general` returns public general applications with platform, plugins and configurations.')]
    public function testThatPublicGeneralCatalogReturnsStructuredPayload(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('items', $responseData);
        self::assertIsArray($responseData['items']);
        self::assertNotEmpty($responseData['items']);

        $first = $responseData['items'][0];
        self::assertArrayHasKey('title', $first);
        self::assertArrayHasKey('slug', $first);
        self::assertArrayHasKey('platform', $first);
        self::assertArrayHasKey('plugins', $first);
        self::assertArrayHasKey('configurations', $first);
    }
}
