<?php

declare(strict_types=1);

namespace App\Tests\Application\Platform\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PublicApplicationListControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/application/public';

    /** @throws Throwable */
    #[TestDox('Test that `GET /v1/application/public` without authentication returns paginated public applications only.')]
    public function testThatPublicListWorksWithoutAuthentication(): void
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
        self::assertArrayHasKey('pagination', $responseData);
        self::assertArrayHasKey('filters', $responseData);

        self::assertCount(2, $responseData['items']);
        self::assertSame(2, $responseData['pagination']['totalItems']);

        $titles = array_column($responseData['items'], 'title');
        self::assertSame(['CRM Growth App', 'Shop Ops App'], $titles);

        foreach ($responseData['items'] as $application) {
            self::assertIsArray($application);
            self::assertArrayHasKey('title', $application);
            self::assertArrayHasKey('description', $application);
            self::assertArrayHasKey('platformName', $application);
            self::assertArrayHasKey('platformKey', $application);
            self::assertArrayHasKey('isOwner', $application);
            self::assertFalse($application['private']);
        }
    }

    /** @throws Throwable */
    #[TestDox('Test that `GET /v1/application/public` supports filters and pagination.')]
    public function testThatPublicListSupportsFiltersAndPagination(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', $this->baseUrl . '?title=shop&platformKey=shop&page=1&limit=1');
        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertCount(1, $responseData['items']);
        self::assertSame('Shop Ops App', $responseData['items'][0]['title']);
        self::assertSame('shop', $responseData['filters']['platformKey']);
        self::assertSame(1, $responseData['pagination']['page']);
        self::assertSame(1, $responseData['pagination']['limit']);
    }
}
