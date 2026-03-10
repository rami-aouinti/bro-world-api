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

    /**
     * @throws Throwable
     */
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

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/application/public` paginates applications (not joined plugin rows) when an application has multiple plugins.')]
    public function testThatPublicListPaginationIsStableWithMultiplePlugins(): void
    {
        $createClient = $this->getTestClient('john-user', 'password-user');
        $createClient->request('POST', self::API_URL_PREFIX . '/v1/profile/applications', content: JSON::encode([
            'platformId' => '40000000-0000-1000-8000-000000000001',
            'title' => 'AA Multi Plugin App',
            'description' => 'Pagination regression guard',
            'plugins' => [
                [
                    'pluginId' => '50000000-0000-1000-8000-000000000001',
                ],
                [
                    'pluginId' => '50000000-0000-1000-8000-000000000002',
                ],
            ],
        ]));
        self::assertSame(Response::HTTP_CREATED, $createClient->getResponse()->getStatusCode());

        $client = $this->getTestClient();
        $client->request('GET', $this->baseUrl . '?page=1&limit=1');
        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertCount(1, $responseData['items']);
        self::assertSame('AA Multi Plugin App', $responseData['items'][0]['title']);
        $pluginKeys = $responseData['items'][0]['pluginKeys'];
        sort($pluginKeys);
        self::assertSame(['calendar', 'chat'], $pluginKeys);
        self::assertSame(3, $responseData['pagination']['totalItems']);

        $client->request('GET', $this->baseUrl . '?page=2&limit=1');
        $page2Response = $client->getResponse();
        $page2Content = $page2Response->getContent();

        self::assertNotFalse($page2Content);
        self::assertSame(Response::HTTP_OK, $page2Response->getStatusCode(), "Response:\n" . $page2Response);

        $page2Data = JSON::decode($page2Content, true);
        self::assertIsArray($page2Data);
        self::assertCount(1, $page2Data['items']);
        self::assertSame('CRM Growth App', $page2Data['items'][0]['title']);
    }

    /**
     * @throws Throwable
     */
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
