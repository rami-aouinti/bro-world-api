<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\User;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use App\User\Infrastructure\DataFixtures\ORM\LoadUserData;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserFriendRequestControllerTest extends WebTestCase
{
    private const string BASE_URL = self::API_URL_PREFIX . '/v1/users';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that friend request can be created.')]
    public function testThatFriendRequestSucceeds(): void
    {
        $requesterId = LoadUserData::getUuidByKey('john-user');
        $addresseeId = LoadUserData::getUuidByKey('john-admin');
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request('POST', self::BASE_URL . '/' . $addresseeId . '/friends/request');
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame('ok', $responseData['status']);
        self::assertSame('PENDING', $responseData['data']['status']);
        self::assertSame($requesterId, $responseData['data']['requesterId']);
        self::assertSame($addresseeId, $responseData['data']['addresseeId']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that user cannot send a friend request to himself.')]
    public function testThatSelfFriendRequestIsForbidden(): void
    {
        $loggedUserId = LoadUserData::getUuidByKey('john-user');
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request('POST', self::BASE_URL . '/' . $loggedUserId . '/friends/request');
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame('Cannot perform this action on yourself.', $responseData['message']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that duplicate friend request returns conflict.')]
    public function testThatDuplicateFriendRequestReturnsConflict(): void
    {
        $addresseeId = LoadUserData::getUuidByKey('john-admin');
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request('POST', self::BASE_URL . '/' . $addresseeId . '/friends/request');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('POST', self::BASE_URL . '/' . $addresseeId . '/friends/request');
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame('Friend request already sent.', $responseData['message']);
    }
}
