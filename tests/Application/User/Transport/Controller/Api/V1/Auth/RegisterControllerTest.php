<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\Auth;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RegisterControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/auth';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `POST /api/v1/auth/register` returns token for valid payload.')]
    public function testThatRegisterActionReturnsToken(): void
    {
        $client = $this->getTestClient();

        $requestData = [
            'email' => 'new-user-register@test.com',
            'password' => 'password-register',
            'repeatPassword' => 'password-register',
        ];

        $client->request(method: 'POST', uri: $this->baseUrl . '/register', content: JSON::encode($requestData));
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertArrayHasKey('token', $responseData);
        self::assertIsString($responseData['token']);
    }
}
