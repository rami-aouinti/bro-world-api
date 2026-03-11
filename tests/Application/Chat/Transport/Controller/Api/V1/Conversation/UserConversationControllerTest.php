<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat\Transport\Controller\Api\V1\Conversation;

use App\General\Domain\Utils\JSON;
use App\Recruit\Infrastructure\DataFixtures\ORM\LoadRecruitChatCalendarScenarioData;
use App\Tests\TestCase\WebTestCase;
use App\User\Infrastructure\DataFixtures\ORM\LoadUserData;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class UserConversationControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/chat/private';

    /** @throws Throwable */
    #[TestDox('GET conversations requires authentication')]
    public function testListRequiresAuthentication(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', $this->baseUrl . '/conversations');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /** @throws Throwable */
    #[TestDox('GET conversations returns items for authenticated user')]
    public function testListNominal(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', $this->baseUrl . '/conversations');

        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('items', $payload);
        self::assertNotEmpty($payload['items']);
    }

    /** @throws Throwable */
    #[TestDox('POST conversation create accepts valid payload and rejects invalid payload')]
    public function testCreateNominalAndValidationError(): void
    {
        $chatId = LoadRecruitChatCalendarScenarioData::getUuidByKey('chat-crm-pipeline-pro');
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('POST', $this->baseUrl . '/chats/' . $chatId . '/conversations', [], [], [], JSON::encode([
            'userId' => LoadUserData::getUuidByKey('john-user'),
        ]));
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());

        $client->request('POST', $this->baseUrl . '/chats/' . $chatId . '/conversations', [], [], [], JSON::encode([]));
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    /** @throws Throwable */
    #[TestDox('PATCH and DELETE conversation endpoints accept for participant and hide unauthorized conversation')]
    public function testPatchDeleteAndUnauthorizedFindOrCreate(): void
    {
        $conversationId = LoadRecruitChatCalendarScenarioData::getUuidByKey('conversation-john-root-scenario');
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('PATCH', $this->baseUrl . '/conversations/' . $conversationId, [], [], [], JSON::encode([
            'userId' => LoadUserData::getUuidByKey('alice'),
        ]));
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());

        $client->request('DELETE', $this->baseUrl . '/conversations/' . $conversationId);
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());

        $otherConversationId = LoadRecruitChatCalendarScenarioData::getUuidByKey('conversation-direct-john-root-john-admin');
        $unauthorizedClient = $this->getTestClient('john-user', 'password-user');
        $unauthorizedClient->request('GET', $this->baseUrl . '/conversations/' . $otherConversationId);
        self::assertSame(Response::HTTP_NOT_FOUND, $unauthorizedClient->getResponse()->getStatusCode());

        $client->request('POST', $this->baseUrl . '/conversation/' . LoadUserData::getUuidByKey('john-admin') . '/user');
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());
    }
}
