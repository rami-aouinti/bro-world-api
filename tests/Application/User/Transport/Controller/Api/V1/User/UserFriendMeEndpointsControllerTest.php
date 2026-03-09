<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\User;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use App\User\Infrastructure\DataFixtures\ORM\LoadUserData;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserFriendMeEndpointsControllerTest extends WebTestCase
{
    private const string BASE_URL = self::API_URL_PREFIX . '/v1/users';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that user can list blocked users from me endpoint.')]
    public function testThatMyBlockedUsersListSucceeds(): void
    {
        $adminId = LoadUserData::getUuidByKey('john-admin');
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request('POST', self::BASE_URL . '/' . $adminId . '/block');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', self::BASE_URL . '/me/friends/blocked');
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame('ok', $responseData['status']);
        self::assertCount(1, $responseData['data']);
        self::assertSame($adminId, $responseData['data'][0]['id']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that user can list sent requests and cancel own pending request.')]
    public function testThatMySentRequestsAndCancelSucceeds(): void
    {
        $adminId = LoadUserData::getUuidByKey('john-admin');
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request('POST', self::BASE_URL . '/' . $adminId . '/friends/request');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', self::BASE_URL . '/me/friends/requests/sent');
        $sentResponse = $client->getResponse();
        $sentContent = $sentResponse->getContent();
        self::assertNotFalse($sentContent);
        self::assertSame(Response::HTTP_OK, $sentResponse->getStatusCode(), "Response:\n" . $sentResponse);

        $sentData = JSON::decode($sentContent, true);
        self::assertSame('ok', $sentData['status']);
        self::assertCount(1, $sentData['data']);
        self::assertSame($adminId, $sentData['data'][0]['id']);

        $client->request('DELETE', self::BASE_URL . '/' . $adminId . '/friends/request');
        $cancelResponse = $client->getResponse();
        $cancelContent = $cancelResponse->getContent();
        self::assertNotFalse($cancelContent);
        self::assertSame(Response::HTTP_OK, $cancelResponse->getStatusCode(), "Response:\n" . $cancelResponse);

        $cancelData = JSON::decode($cancelContent, true);
        self::assertSame('ok', $cancelData['status']);

        $client->request('GET', self::BASE_URL . '/me/friends/requests/sent');
        $afterResponse = $client->getResponse();
        $afterContent = $afterResponse->getContent();
        self::assertNotFalse($afterContent);
        self::assertSame(Response::HTTP_OK, $afterResponse->getStatusCode(), "Response:\n" . $afterResponse);

        $afterData = JSON::decode($afterContent, true);
        self::assertSame('ok', $afterData['status']);
        self::assertCount(0, $afterData['data']);
    }
}
