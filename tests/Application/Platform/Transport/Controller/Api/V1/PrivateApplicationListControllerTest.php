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
class PrivateApplicationListControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/application/private';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/application/private` requires authentication.')]
    public function testThatPrivateListRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/application/private` returns public applications and authenticated user applications.')]
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
        self::assertCount(3, $responseData);

        $titles = array_column($responseData, 'title');
        self::assertSame(
            [
                'CRM Growth App',
                'Recruit Lite App',
                'Shop Ops App',
            ],
            $titles,
        );

        foreach ($responseData as $application) {
            self::assertArrayHasKey('isOwner', $application);
            self::assertTrue($application['isOwner']);
        }
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/application/private` indicates ownership for non owner authenticated users.')]
    public function testThatPrivateListOwnershipIsFalseForNonOwner(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertCount(2, $responseData);

        foreach ($responseData as $application) {
            self::assertArrayHasKey('isOwner', $application);
            self::assertFalse($application['isOwner']);
        }
    }
}
