<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\User;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use App\User\Infrastructure\DataFixtures\ORM\LoadUserData;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserBlockControllerTest extends WebTestCase
{
    private const string BASE_URL = self::API_URL_PREFIX . '/v1/users';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that block prevents friend request, then unblock allows valid friend flow again.')]
    public function testThatBlockThenUnblockControlsFriendFlow(): void
    {
        $userId = LoadUserData::getUuidByKey('john-user');
        $adminId = LoadUserData::getUuidByKey('john-admin');

        $userClient = $this->getTestClient('john-user', 'password-user');
        $adminClient = $this->getTestClient('john-admin', 'password-admin');

        $userClient->request('POST', self::BASE_URL . '/' . $adminId . '/block');
        $blockResponse = $userClient->getResponse();
        $blockContent = $blockResponse->getContent();
        self::assertNotFalse($blockContent);
        self::assertSame(Response::HTTP_OK, $blockResponse->getStatusCode(), "Response:\n" . $blockResponse);
        $blockData = JSON::decode($blockContent, true);
        self::assertSame('BLOCKED', $blockData['data']['status']);
        self::assertSame($userId, $blockData['data']['blockedById']);

        $adminClient->request('POST', self::BASE_URL . '/' . $userId . '/friends/request');
        $requestWhileBlockedResponse = $adminClient->getResponse();
        $requestWhileBlockedContent = $requestWhileBlockedResponse->getContent();
        self::assertNotFalse($requestWhileBlockedContent);
        self::assertSame(Response::HTTP_FORBIDDEN, $requestWhileBlockedResponse->getStatusCode(), "Response:\n" . $requestWhileBlockedResponse);
        $requestWhileBlockedData = JSON::decode($requestWhileBlockedContent, true);
        self::assertSame(
            'Cannot send a friend request while a block is active between both users.',
            $requestWhileBlockedData['message']
        );

        $userClient->request('DELETE', self::BASE_URL . '/' . $adminId . '/block');
        $unblockResponse = $userClient->getResponse();
        $unblockContent = $unblockResponse->getContent();
        self::assertNotFalse($unblockContent);
        self::assertSame(Response::HTTP_OK, $unblockResponse->getStatusCode(), "Response:\n" . $unblockResponse);
        $unblockData = JSON::decode($unblockContent, true);
        self::assertSame('REJECTED', $unblockData['data']['status']);

        $adminClient->request('POST', self::BASE_URL . '/' . $userId . '/friends/request');
        $newRequestResponse = $adminClient->getResponse();
        $newRequestContent = $newRequestResponse->getContent();
        self::assertNotFalse($newRequestContent);
        self::assertSame(Response::HTTP_OK, $newRequestResponse->getStatusCode(), "Response:\n" . $newRequestResponse);
        $newRequestData = JSON::decode($newRequestContent, true);
        self::assertSame('PENDING', $newRequestData['data']['status']);

        $userClient->request('POST', self::BASE_URL . '/' . $adminId . '/friends/accept');
        $acceptResponse = $userClient->getResponse();
        $acceptContent = $acceptResponse->getContent();
        self::assertNotFalse($acceptContent);
        self::assertSame(Response::HTTP_OK, $acceptResponse->getStatusCode(), "Response:\n" . $acceptResponse);
        $acceptData = JSON::decode($acceptContent, true);
        self::assertSame('ACCEPTED', $acceptData['data']['status']);
    }
}
