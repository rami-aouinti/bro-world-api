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
class PublicApplicationListControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/application/public';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/application/public` without authentication returns all public applications only.')]
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
        self::assertCount(2, $responseData);

        $titles = array_column($responseData, 'title');
        self::assertSame(
            [
                'CRM Growth App',
                'Shop Ops App',
            ],
            $titles,
        );

        foreach ($responseData as $application) {
            self::assertIsArray($application);
            self::assertArrayHasKey('id', $application);
            self::assertArrayHasKey('title', $application);
            self::assertArrayHasKey('status', $application);
            self::assertArrayHasKey('private', $application);
            self::assertArrayHasKey('platformId', $application);
            self::assertArrayHasKey('platformName', $application);
            self::assertArrayHasKey('ownerId', $application);
            self::assertFalse($application['private']);
        }
    }
}
