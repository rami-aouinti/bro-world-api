<?php

declare(strict_types=1);

namespace App\Tests\Application\Platform\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrivateApplicationListControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/application/private';

    /** @throws Throwable */
    #[TestDox('Test that `GET /v1/application/private` requires authentication.')]
    public function testThatPrivateListRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), "Response:\n" . $response);
    }

    /** @throws Throwable */
    #[TestDox('Test that `GET /v1/application/private` returns public and current user applications with pagination.')]
    public function testThatPrivateListReturnsPublicAndCurrentUserApplications(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertCount(3, $responseData['items']);

        $titles = array_column($responseData['items'], 'title');
        self::assertSame(['CRM Growth App', 'Recruit Lite App', 'Shop Ops App'], $titles);

        foreach ($responseData['items'] as $application) {
            self::assertArrayHasKey('isOwner', $application);
            self::assertTrue($application['isOwner']);
        }
    }

    /** @throws Throwable */
    #[TestDox('Test that `GET /v1/application/private` supports ownership and filtering.')]
    public function testThatPrivateListOwnershipIsFalseForNonOwner(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request('GET', $this->baseUrl . '?platformKey=crm&limit=10');
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertSame('crm', $responseData['filters']['platformKey']);

        foreach ($responseData['items'] as $application) {
            self::assertSame('crm', $application['platformKey']);

            if ($application['title'] === 'John User Private App') {
                self::assertTrue($application['isOwner']);
                continue;
            }

            self::assertFalse($application['isOwner']);
        }
    }
}
