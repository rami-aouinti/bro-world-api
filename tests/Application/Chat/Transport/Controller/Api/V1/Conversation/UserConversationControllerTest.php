<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\General\Domain\Utils\JSON;
use App\Recruit\Infrastructure\DataFixtures\ORM\LoadRecruitChatCalendarScenarioData;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\DataFixtures\ORM\LoadUserData;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class UserConversationControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/chat/private';

    /**
     * @throws Throwable
     */
    #[TestDox('GET conversations requires authentication')]
    public function testListRequiresAuthentication(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', $this->baseUrl . '/conversations');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
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

    /**
     * @throws Throwable
     */
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

    /**
     * @throws Throwable
     */
    #[TestDox('GET conversations excludes archived conversations from items and pagination total, including with page/limit')]
    public function testListExcludesArchivedConversationFromItemsAndTotal(): void
    {
        $this->createActiveAndArchivedConversationsForJohnRoot();
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', $this->baseUrl . '/conversations?message=archived-filter-token');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $payload = JSON::decode($content, true);

        self::assertCount(1, $payload['items']);
        self::assertSame(1, $payload['pagination']['totalItems']);

        $client->request('GET', $this->baseUrl . '/conversations?message=archived-filter-token&page=2&limit=1');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $pagedContent = $client->getResponse()->getContent();
        self::assertNotFalse($pagedContent);
        $pagedPayload = JSON::decode($pagedContent, true);

        self::assertCount(0, $pagedPayload['items']);
        self::assertSame(1, $pagedPayload['pagination']['totalItems']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET private conversations message filter ignores deleted messages for user listing')]
    public function testListByUserMessageFilterIgnoresDeletedMessages(): void
    {
        $this->createConversationWithDeletedKeywordMessage('deleted-only-filter-token-user');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', $this->baseUrl . '/conversations?message=deleted-only-filter-token-user');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $payload = JSON::decode($content, true);

        self::assertCount(0, $payload['items']);
        self::assertSame(0, $payload['pagination']['totalItems']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET application chat conversations message filter ignores deleted messages for chat listing')]
    public function testListByChatIdMessageFilterIgnoresDeletedMessages(): void
    {
        $chatId = $this->createConversationWithDeletedKeywordMessage('deleted-only-filter-token-chat');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/chat/crm-pipeline-pro/chats/' . $chatId . '/conversations?message=deleted-only-filter-token-chat');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $payload = JSON::decode($content, true);

        self::assertCount(0, $payload['items']);
        self::assertSame(0, $payload['pagination']['totalItems']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET application private chat conversations message filter ignores deleted messages for chat and user listing')]
    public function testListByChatIdAndUserMessageFilterIgnoresDeletedMessages(): void
    {
        $chatId = $this->createConversationWithDeletedKeywordMessage('deleted-only-filter-token-chat-user');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/chat/crm-pipeline-pro/private/chats/' . $chatId . '/conversations?message=deleted-only-filter-token-chat-user');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $payload = JSON::decode($content, true);

        self::assertCount(0, $payload['items']);
        self::assertSame(0, $payload['pagination']['totalItems']);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('GET application scoped conversations returns CRM General conversation with all CRM employees')]
    public function testListByApplicationScopeReturnsCrmGeneralConversation(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/chat/crm-general-core/private/applications/conversations');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $payload = JSON::decode($content, true);

        self::assertNotEmpty($payload['items']);
        self::assertSame('General', $payload['items'][0]['title'] ?? null);
        self::assertGreaterThanOrEqual(4, count($payload['items'][0]['participants'] ?? []));
    }

    /**
     * @throws Throwable
     */
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

    private function createActiveAndArchivedConversationsForJohnRoot(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        /** @var Conversation|null $seedConversation */
        $seedConversation = $entityManager->getRepository(Conversation::class)->find(
            LoadRecruitChatCalendarScenarioData::getUuidByKey('conversation-john-root-scenario')
        );

        self::assertInstanceOf(Conversation::class, $seedConversation);

        $chat = $seedConversation->getChat();
        $johnRoot = $this->getUserReference($entityManager, 'john-root');
        $johnAdmin = $this->getUserReference($entityManager, 'john-admin');

        $activeConversation = (new Conversation())
            ->setChat($chat);

        $archivedConversation = (new Conversation())
            ->setChat($chat)
            ->setArchivedAt(new DateTimeImmutable('now'));

        $this->addParticipant($activeConversation, $johnRoot, $johnAdmin);
        $this->addParticipant($archivedConversation, $johnRoot, $johnAdmin);

        $this->addMessage($activeConversation, $johnRoot, 'archived-filter-token active');
        $this->addMessage($archivedConversation, $johnRoot, 'archived-filter-token archived');

        $entityManager->persist($activeConversation);
        $entityManager->persist($archivedConversation);
        $entityManager->flush();
    }

    private function addParticipant(Conversation $conversation, User ...$users): void
    {
        foreach ($users as $user) {
            $participant = (new ConversationParticipant())
                ->setConversation($conversation)
                ->setUser($user);

            $conversation->addParticipant($participant);
        }
    }

    private function addMessage(Conversation $conversation, User $sender, string $content): void
    {
        $message = (new ChatMessage())
            ->setConversation($conversation)
            ->setSender($sender)
            ->setContent($content);

        $conversation->addMessage($message);
        $conversation->setLastMessageAt(new DateTimeImmutable('now'));
    }

    private function createConversationWithDeletedKeywordMessage(string $keyword): string
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        /** @var Conversation|null $seedConversation */
        $seedConversation = $entityManager->getRepository(Conversation::class)->find(
            LoadRecruitChatCalendarScenarioData::getUuidByKey('conversation-john-root-scenario')
        );

        self::assertInstanceOf(Conversation::class, $seedConversation);

        $chat = $seedConversation->getChat();
        $johnRoot = $this->getUserReference($entityManager, 'john-root');
        $johnAdmin = $this->getUserReference($entityManager, 'john-admin');

        $conversation = (new Conversation())
            ->setChat($chat);

        $this->addParticipant($conversation, $johnRoot, $johnAdmin);

        $deletedMessage = (new ChatMessage())
            ->setConversation($conversation)
            ->setSender($johnRoot)
            ->setContent('contains ' . $keyword)
            ->setDeletedAt(new DateTimeImmutable('now'));

        $visibleMessage = (new ChatMessage())
            ->setConversation($conversation)
            ->setSender($johnAdmin)
            ->setContent('message without keyword');

        $conversation->addMessage($deletedMessage);
        $conversation->addMessage($visibleMessage);
        $conversation->setLastMessageAt($visibleMessage->getCreatedAt());

        $entityManager->persist($conversation);
        $entityManager->flush();

        return $chat->getId();
    }

    private function getUserReference(EntityManagerInterface $entityManager, string $key): User
    {
        $user = $entityManager->getRepository(User::class)->find(
            LoadUserData::getUuidByKey($key)
        );

        self::assertNotNull($user);

        return $user;
    }
}
