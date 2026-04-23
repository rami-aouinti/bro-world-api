<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\UserStory;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GetActiveStoriesControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/private/stories';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/private/stories` requires authentication.')]
    public function testThatGetActiveStoriesRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that authenticated user can get visible active stories (mine + friends).')]
    public function testThatAuthenticatedUserCanGetVisibleStories(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', $this->baseUrl);
        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('stories', $responseData);
        self::assertIsArray($responseData['stories']);
        self::assertNotEmpty($responseData['stories']);

        $firstGroup = $responseData['stories'][0] ?? null;
        self::assertIsArray($firstGroup);
        self::assertSame(true, $firstGroup['owner'] ?? null);
        self::assertArrayHasKey('stories', $firstGroup);
        self::assertIsArray($firstGroup['stories']);
        self::assertNotEmpty($firstGroup['stories']);
    }
}
