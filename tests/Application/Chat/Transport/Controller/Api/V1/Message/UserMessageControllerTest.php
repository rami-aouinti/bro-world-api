<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat\Transport\Controller\Api\V1\Message;

use App\General\Domain\Utils\JSON;
use App\Recruit\Infrastructure\DataFixtures\ORM\LoadRecruitChatCalendarScenarioData;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class UserMessageControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/chat/private';

    /**
     * @throws Throwable
     */
    #[TestDox('Message list/create/patch/delete/read endpoints cover nominal + validation + authorization')]
    public function testMessageEndpoints(): void
    {
        $conversationId = LoadRecruitChatCalendarScenarioData::getUuidByKey('conversation-john-root-scenario');
        $messageId = LoadRecruitChatCalendarScenarioData::getUuidByKey('message-john-root-scenario-from-john-root');

        $anonymous = $this->getTestClient();
        $anonymous->request('GET', $this->baseUrl . '/conversations/' . $conversationId);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $anonymous->getResponse()->getStatusCode());

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', $this->baseUrl . '/conversations/' . $conversationId);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('items', $payload);

        $client->request('POST', $this->baseUrl . '/conversations/' . $conversationId . '/messages', [], [], [], JSON::encode([
            'content' => 'Nouveau message de test fonctionnel',
        ]));
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());
        $createContent = $client->getResponse()->getContent();
        self::assertNotFalse($createContent);
        $createPayload = JSON::decode($createContent, true);
        self::assertIsArray($createPayload);
        self::assertSame(['operationId'], array_keys($createPayload));
        self::assertIsString($createPayload['operationId']);

        $client->request('POST', $this->baseUrl . '/conversations/' . $conversationId . '/messages', [], [], [], JSON::encode([
            'content' => '',
        ]));
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $client->request('PATCH', $this->baseUrl . '/messages/' . $messageId, [], [], [], JSON::encode([
            'read' => true,
            'content' => 'Message édité via test',
        ]));
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());
        $patchContent = $client->getResponse()->getContent();
        self::assertNotFalse($patchContent);
        $patchPayload = JSON::decode($patchContent, true);
        self::assertIsArray($patchPayload);
        self::assertSame(['operationId'], array_keys($patchPayload));
        self::assertIsString($patchPayload['operationId']);

        $client->request('PATCH', $this->baseUrl . '/messages/' . $messageId, [], [], [], JSON::encode([
            'read' => 'yes',
        ]));
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $client->request('POST', $this->baseUrl . '/conversations/' . $conversationId . '/messages/read');
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());
        $markReadContent = $client->getResponse()->getContent();
        self::assertNotFalse($markReadContent);
        $markReadPayload = JSON::decode($markReadContent, true);
        self::assertIsArray($markReadPayload);
        self::assertSame(['operationId'], array_keys($markReadPayload));
        self::assertIsString($markReadPayload['operationId']);

        $unauthorizedClient = $this->getTestClient('john-user', 'password-user');
        $directConversationId = LoadRecruitChatCalendarScenarioData::getUuidByKey('conversation-direct-john-root-john-admin');
        $unauthorizedClient->request('GET', $this->baseUrl . '/conversations/' . $directConversationId);
        self::assertSame(Response::HTTP_NOT_FOUND, $unauthorizedClient->getResponse()->getStatusCode());

        $client->request('DELETE', $this->baseUrl . '/messages/' . $messageId);
        self::assertSame(Response::HTTP_ACCEPTED, $client->getResponse()->getStatusCode());
        $deleteContent = $client->getResponse()->getContent();
        self::assertNotFalse($deleteContent);
        $deletePayload = JSON::decode($deleteContent, true);
        self::assertIsArray($deletePayload);
        self::assertSame(['operationId'], array_keys($deletePayload));
        self::assertIsString($deletePayload['operationId']);
    }
}
