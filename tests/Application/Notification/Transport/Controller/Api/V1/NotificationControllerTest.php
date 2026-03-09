<?php

declare(strict_types=1);

namespace App\Tests\Application\Notification\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use App\User\Infrastructure\DataFixtures\ORM\LoadUserData;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function array_keys;

class NotificationControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/notifications';

    /** @throws Throwable */
    #[TestDox('Test that `GET /v1/notifications` requires authentication.')]
    public function testThatListRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', $this->baseUrl);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /** @throws Throwable */
    #[TestDox('Test that `GET /v1/notifications` returns only notifications for the authenticated user.')]
    public function testThatListReturnsOnlyCurrentUserNotifications(): void
    {
        $recipientId = LoadUserData::getUuidByKey('john-root');
        $otherRecipientId = LoadUserData::getUuidByKey('john-admin');
        $fromId = LoadUserData::getUuidByKey('john-user');

        $rootNotification = $this->createNotification('root-visible', $recipientId, $fromId);
        $otherUserNotification = $this->createNotification('admin-hidden', $otherRecipientId, $fromId);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', $this->baseUrl);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);

        $notificationIds = array_column($payload, 'id');
        self::assertContains($rootNotification['id'], $notificationIds);
        self::assertNotContains($otherUserNotification['id'], $notificationIds);

        $rootNotificationInList = null;
        foreach ($payload as $item) {
            if (($item['id'] ?? null) === $rootNotification['id']) {
                $rootNotificationInList = $item;
                break;
            }
        }

        self::assertIsArray($rootNotificationInList);
        self::assertArrayHasKey('from', $rootNotificationInList);
        self::assertIsArray($rootNotificationInList['from']);
        self::assertSame(['firstName', 'lastName', 'photo'], array_keys($rootNotificationInList['from']));
    }

    /** @throws Throwable */
    #[TestDox('Test that `GET /v1/notifications/{id}` for another user notification is denied.')]
    public function testThatDetailForOtherUserNotificationIsDenied(): void
    {
        $otherRecipientId = LoadUserData::getUuidByKey('john-admin');

        $notification = $this->createNotification('other-user-detail', $otherRecipientId);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', $this->baseUrl . '/' . $notification['id']);

        $response = $client->getResponse();
        self::assertContains($response->getStatusCode(), [Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND], "Response:\n" . $response);
    }

    /** @throws Throwable */
    #[TestDox('Test that `POST /v1/notifications` with valid payload returns 201 and normalized from fields only.')]
    public function testThatCreateWithValidPayloadReturnsCreated(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            $this->baseUrl,
            [],
            [],
            [],
            JSON::encode([
                'title' => 'valid-notification',
                'description' => 'description',
                'type' => 'system',
                'toId' => LoadUserData::getUuidByKey('john-root'),
                'fromId' => LoadUserData::getUuidByKey('john-user'),
            ])
        );

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('id', $payload);
        self::assertArrayHasKey('from', $payload);
        self::assertIsArray($payload['from']);
        self::assertSame(['firstName', 'lastName', 'photo'], array_keys($payload['from']));
    }

    /** @throws Throwable */
    #[TestDox('Test that `POST /v1/notifications` with invalid payload returns 400.')]
    public function testThatCreateWithInvalidPayloadReturnsBadRequest(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            $this->baseUrl,
            [],
            [],
            [],
            JSON::encode([
                'title' => '',
                'type' => 'system',
                'toId' => LoadUserData::getUuidByKey('john-root'),
            ])
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    private function createNotification(string $title, string $toId, ?string $fromId = null): array
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $payload = [
            'title' => $title,
            'description' => 'seed for tests',
            'type' => 'system',
            'toId' => $toId,
        ];

        if ($fromId !== null) {
            $payload['fromId'] = $fromId;
        }

        $client->request('POST', $this->baseUrl, [], [], [], JSON::encode($payload));

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $responsePayload = JSON::decode($content, true);
        self::assertIsArray($responsePayload);

        return $responsePayload;
    }
}
