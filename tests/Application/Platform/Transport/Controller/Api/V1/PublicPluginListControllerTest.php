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
class PublicPluginListControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/plugin/public';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/plugin/public` without authentication returns enabled and non-private plugins only.')]
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
        self::assertCount(3, $responseData);

        foreach ($responseData as $plugin) {
            self::assertIsArray($plugin);
            self::assertArrayHasKey('id', $plugin);
            self::assertArrayHasKey('name', $plugin);
            self::assertArrayHasKey('description', $plugin);
            self::assertArrayHasKey('photo', $plugin);
            self::assertArrayHasKey('pluginKey', $plugin);
            self::assertArrayNotHasKey('private', $plugin);
            self::assertArrayNotHasKey('enabled', $plugin);
            self::assertStringStartsWith('https://ui-avatars.com/api/?name=', $plugin['photo']);
            self::assertContains($plugin['pluginKey'], ['calendar', 'chat', 'blog', 'language']);
        }

        $names = array_column($responseData, 'name');
        self::assertSame(
            [
                'Analytics Booster',
                'CRM Assistant',
                'Knowledge Base Connector',
            ],
            $names,
        );
    }
}
