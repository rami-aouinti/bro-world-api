<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\Auth;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SocialLoginControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/auth';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `POST /api/v1/auth/social_login` creates a social user and returns token.')]
    public function testThatSocialLoginCreatesUserAndReturnsToken(): void
    {
        $client = $this->getTestClient();

        $requestData = [
            'email' => 'new-social-user@test.com',
            'provider' => 'github',
            'providerId' => 'new-social-user-github-id',
        ];

        $client->request(method: 'POST', uri: $this->baseUrl . '/social_login', content: JSON::encode($requestData));
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertArrayHasKey('token', $responseData);
        self::assertIsString($responseData['token']);
    }
}
