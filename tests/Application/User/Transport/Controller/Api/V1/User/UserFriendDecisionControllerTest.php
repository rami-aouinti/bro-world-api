<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\User;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use App\User\Infrastructure\DataFixtures\ORM\LoadUserData;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserFriendDecisionControllerTest extends WebTestCase
{
    private const string BASE_URL = self::API_URL_PREFIX . '/v1/users';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that addressee can accept pending friend request.')]
    public function testThatAcceptFriendRequestSucceeds(): void
    {
        $requesterId = LoadUserData::getUuidByKey('john-user');
        $addresseeId = LoadUserData::getUuidByKey('john-admin');

        $requesterClient = $this->getTestClient('john-user', 'password-user');
        $requesterClient->request('POST', self::BASE_URL . '/' . $addresseeId . '/friends/request');
        self::assertSame(Response::HTTP_OK, $requesterClient->getResponse()->getStatusCode());

        $addresseeClient = $this->getTestClient('john-admin', 'password-admin');
        $addresseeClient->request('POST', self::BASE_URL . '/' . $requesterId . '/friends/accept');
        $response = $addresseeClient->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame('ok', $responseData['status']);
        self::assertSame('ACCEPTED', $responseData['data']['status']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that addressee can reject pending friend request.')]
    public function testThatRejectFriendRequestSucceeds(): void
    {
        $requesterId = LoadUserData::getUuidByKey('john-user');
        $addresseeId = LoadUserData::getUuidByKey('john-admin');

        $requesterClient = $this->getTestClient('john-user', 'password-user');
        $requesterClient->request('POST', self::BASE_URL . '/' . $addresseeId . '/friends/request');
        self::assertSame(Response::HTTP_OK, $requesterClient->getResponse()->getStatusCode());

        $addresseeClient = $this->getTestClient('john-admin', 'password-admin');
        $addresseeClient->request('POST', self::BASE_URL . '/' . $requesterId . '/friends/reject');
        $response = $addresseeClient->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame('ok', $responseData['status']);
        self::assertSame('REJECTED', $responseData['data']['status']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that only addressee can accept or reject a pending friend request.')]
    public function testThatOnlyAddresseeCanAcceptOrReject(): void
    {
        $requesterId = LoadUserData::getUuidByKey('john-user');
        $addresseeId = LoadUserData::getUuidByKey('john-admin');

        $requesterClient = $this->getTestClient('john-user', 'password-user');
        $requesterClient->request('POST', self::BASE_URL . '/' . $addresseeId . '/friends/request');
        self::assertSame(Response::HTTP_OK, $requesterClient->getResponse()->getStatusCode());

        $requesterClient->request('POST', self::BASE_URL . '/' . $addresseeId . '/friends/accept');
        $acceptResponse = $requesterClient->getResponse();
        $acceptContent = $acceptResponse->getContent();
        self::assertNotFalse($acceptContent);
        self::assertSame(Response::HTTP_FORBIDDEN, $acceptResponse->getStatusCode(), "Response:\n" . $acceptResponse);
        $acceptResponseData = JSON::decode($acceptContent, true);
        self::assertSame('Only the addressee can accept this friend request.', $acceptResponseData['message']);

        $requesterClient->request('POST', self::BASE_URL . '/' . $addresseeId . '/friends/reject');
        $rejectResponse = $requesterClient->getResponse();
        $rejectContent = $rejectResponse->getContent();
        self::assertNotFalse($rejectContent);
        self::assertSame(Response::HTTP_FORBIDDEN, $rejectResponse->getStatusCode(), "Response:\n" . $rejectResponse);
        $rejectResponseData = JSON::decode($rejectContent, true);
        self::assertSame('Only the addressee can reject this friend request.', $rejectResponseData['message']);

        // Ensure request is still pending and can be accepted by addressee.
        $addresseeClient = $this->getTestClient('john-admin', 'password-admin');
        $addresseeClient->request('POST', self::BASE_URL . '/' . $requesterId . '/friends/accept');
        self::assertSame(Response::HTTP_OK, $addresseeClient->getResponse()->getStatusCode());
    }
}
