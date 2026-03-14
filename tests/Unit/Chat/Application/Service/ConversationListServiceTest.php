<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Application\Service;

use App\Chat\Application\Service\ConversationListService;
use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Domain\Enum\ConversationType;
use App\Chat\Domain\Repository\Interfaces\ConversationRepositoryInterface;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ConversationListServiceTest extends TestCase
{
    public function testGetByUserReturnsCacheHitWithoutRepositoryCall(): void
    {
        $repo = $this->createMock(ConversationRepositoryInterface::class);
        $repo->expects(self::never())->method('findByUser');

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->willReturn([
                'items' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => 20,
                    'totalItems' => 0,
                    'totalPages' => 0,
                ],
            ]);

        $service = new ConversationListService($repo, $cache, $elastic, $this->createMock(CacheKeyConventionService::class));
        $result = $service->getByUser($this->mockUser(), [
            'message' => 'foo',
        ], 1, 20);

        self::assertSame([
            'message' => 'foo',
        ], $result['filters']);
    }

    public function testGetByUserCacheMissCallsRepository(): void
    {
        $repo = $this->createMock(ConversationRepositoryInterface::class);
        $repo->expects(self::once())->method('findByUser')->willReturn([]);
        $repo->expects(self::once())->method('countByUser')->willReturn(0);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('search')->willReturn([
            'hits' => [
                'hits' => [],
            ],
        ]);

        $item = $this->createMock(ItemInterface::class);
        $item->expects(self::once())->method('expiresAfter')->with(120);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $service = new ConversationListService($repo, $cache, $elastic, $this->createMock(CacheKeyConventionService::class));
        $result = $service->getByUser($this->mockUser(), [
            'message' => 'foo',
        ], 1, 20);

        self::assertSame(0, $result['pagination']['totalItems']);
    }

    public function testGetByUserFallsBackToDatabaseWhenElasticThrows(): void
    {
        $repo = $this->createMock(ConversationRepositoryInterface::class);
        $repo->expects(self::once())->method('findByUser')->with(self::anything(), self::anything(), 1, 20, null)->willReturn([]);
        $repo->expects(self::once())->method('countByUser')->with(self::anything(), self::anything(), null)->willReturn(0);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('search')->willThrowException(new \RuntimeException('ES down'));

        $item = $this->createMock(ItemInterface::class);
        $item->expects(self::once())->method('expiresAfter')->with(120);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $service = new ConversationListService($repo, $cache, $elastic, $this->createMock(CacheKeyConventionService::class));
        $result = $service->getByUser($this->mockUser(), [
            'message' => 'foo',
        ], 1, 20);

        self::assertSame([], $result['items']);
    }

    public function testGetByUserReturnsLightConversationStructureWithLastMessage(): void
    {
        $connectedUser = $this->mockUser();

        $sender = $this->createMock(User::class);
        $sender->method('getId')->willReturn('sender-id');
        $sender->method('getFirstName')->willReturn('Sender');
        $sender->method('getLastName')->willReturn('User');
        $sender->method('getPhoto')->willReturn(null);

        $readMessage = $this->createMock(ChatMessage::class);
        $readMessage->method('getId')->willReturn('message-read-id');
        $readMessage->method('getContent')->willReturn('older message');
        $readMessage->method('getSender')->willReturn($sender);
        $readMessage->method('getDeletedAt')->willReturn(null);
        $readMessage->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'));

        $message = $this->createMock(ChatMessage::class);
        $message->method('getId')->willReturn('message-id');
        $message->method('getContent')->willReturn(str_repeat('hello', 80));
        $message->method('getSender')->willReturn($sender);
        $message->method('getDeletedAt')->willReturn(null);
        $message->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T12:00:00+00:00'));

        $participant = $this->createMock(ConversationParticipant::class);
        $participant->method('getUser')->willReturn($connectedUser);
        $participant->method('getId')->willReturn('participant-id');
        $participant->method('getLastReadMessageAt')->willReturn(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'));

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn('conversation-id');
        $conversation->method('getChat')->willReturn(null);
        $conversation->method('getType')->willReturn(ConversationType::DIRECT);
        $conversation->method('getTitle')->willReturn('Conversation');
        $conversation->method('getParticipants')->willReturn(new ArrayCollection([$participant]));
        $conversation->method('getMessages')->willReturn(new ArrayCollection([$readMessage, $message]));
        $conversation->method('getLastMessageAt')->willReturn(null);
        $conversation->method('getArchivedAt')->willReturn(null);
        $conversation->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'));

        $repo = $this->createMock(ConversationRepositoryInterface::class);
        $repo->expects(self::once())->method('findByUser')->willReturn([$conversation]);
        $repo->expects(self::once())->method('countByUser')->willReturn(1);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::never())->method('search');

        $cacheKeyConventionService = $this->createMock(CacheKeyConventionService::class);
        $cacheKeyConventionService->expects(self::once())
            ->method('buildPrivateConversationKey')
            ->willReturn('cache-key');

        $item = $this->createMock(ItemInterface::class);
        $item->expects(self::once())->method('expiresAfter')->with(120);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $service = new ConversationListService($repo, $cache, $elastic, $cacheKeyConventionService);
        $result = $service->getByUser($connectedUser, [], 1, 20);

        self::assertArrayNotHasKey('messages', $result['items'][0]);
        self::assertArrayHasKey('lastMessage', $result['items'][0]);
        self::assertSame('message-id', $result['items'][0]['lastMessage']['id']);
        self::assertSame(str_repeat('hello', 56), $result['items'][0]['lastMessage']['content']);
        self::assertSame(280, mb_strlen($result['items'][0]['lastMessage']['content']));
        self::assertSame('sender-id', $result['items'][0]['lastMessage']['sender']['id']);
        self::assertSame('2024-01-01T12:00:00+00:00', $result['items'][0]['lastMessage']['createdAt']);
        self::assertSame(1, $result['items'][0]['unreadMessagesCount']);
    }

    public function testGetByUserReturnsNullLastMessageWhenAllMessagesAreDeleted(): void
    {
        $connectedUser = $this->mockUser();
        $sender = $this->createMock(User::class);
        $sender->method('getId')->willReturn('sender-id');

        $deletedMessage = $this->createMock(ChatMessage::class);
        $deletedMessage->method('getId')->willReturn('deleted-message-id');
        $deletedMessage->method('getContent')->willReturn('deleted');
        $deletedMessage->method('getSender')->willReturn($sender);
        $deletedMessage->method('getDeletedAt')->willReturn(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'));
        $deletedMessage->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T09:00:00+00:00'));

        $participant = $this->createMock(ConversationParticipant::class);
        $participant->method('getUser')->willReturn($connectedUser);
        $participant->method('getId')->willReturn('participant-id');
        $participant->method('getLastReadMessageAt')->willReturn(null);

        $conversation = $this->createConversation([$participant], [$deletedMessage]);

        $result = $this->fetchConversationListFor($connectedUser, [$conversation]);

        self::assertNull($result['items'][0]['lastMessage']);
        self::assertSame(0, $result['items'][0]['unreadMessagesCount']);
    }

    public function testGetByUserWithConversationWithoutConnectedParticipantReturnsZeroUnreadAndLastMessage(): void
    {
        $connectedUser = $this->mockUser();

        $otherUser = $this->createMock(User::class);
        $otherUser->method('getId')->willReturn('other-user-id');

        $sender = $this->createMock(User::class);
        $sender->method('getId')->willReturn('sender-id');

        $participant = $this->createMock(ConversationParticipant::class);
        $participant->method('getUser')->willReturn($otherUser);
        $participant->method('getId')->willReturn('participant-id');
        $participant->method('getLastReadMessageAt')->willReturn(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'));

        $message = $this->createMock(ChatMessage::class);
        $message->method('getId')->willReturn('message-id');
        $message->method('getContent')->willReturn('latest message');
        $message->method('getSender')->willReturn($sender);
        $message->method('getDeletedAt')->willReturn(null);
        $message->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T12:00:00+00:00'));

        $conversation = $this->createConversation([$participant], [$message]);

        $result = $this->fetchConversationListFor($connectedUser, [$conversation]);

        self::assertSame(0, $result['items'][0]['unreadMessagesCount']);
        self::assertSame('message-id', $result['items'][0]['lastMessage']['id']);
    }

    public function testGetByUserUnreadMessagesCountWhenLastReadMessageAtIsNull(): void
    {
        $connectedUser = $this->mockUser();

        $sender = $this->createMock(User::class);
        $sender->method('getId')->willReturn('sender-id');

        $ownMessage = $this->createMock(ChatMessage::class);
        $ownMessage->method('getId')->willReturn('own-message-id');
        $ownMessage->method('getContent')->willReturn('my message');
        $ownMessage->method('getSender')->willReturn($connectedUser);
        $ownMessage->method('getDeletedAt')->willReturn(null);
        $ownMessage->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T09:00:00+00:00'));

        $incomingMessage = $this->createMock(ChatMessage::class);
        $incomingMessage->method('getId')->willReturn('incoming-message-id');
        $incomingMessage->method('getContent')->willReturn('incoming');
        $incomingMessage->method('getSender')->willReturn($sender);
        $incomingMessage->method('getDeletedAt')->willReturn(null);
        $incomingMessage->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'));

        $deletedIncomingMessage = $this->createMock(ChatMessage::class);
        $deletedIncomingMessage->method('getId')->willReturn('deleted-incoming-message-id');
        $deletedIncomingMessage->method('getContent')->willReturn('deleted incoming');
        $deletedIncomingMessage->method('getSender')->willReturn($sender);
        $deletedIncomingMessage->method('getDeletedAt')->willReturn(new \DateTimeImmutable('2024-01-01T10:30:00+00:00'));
        $deletedIncomingMessage->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T10:15:00+00:00'));

        $participant = $this->createMock(ConversationParticipant::class);
        $participant->method('getUser')->willReturn($connectedUser);
        $participant->method('getId')->willReturn('participant-id');
        $participant->method('getLastReadMessageAt')->willReturn(null);

        $conversation = $this->createConversation([$participant], [$ownMessage, $incomingMessage, $deletedIncomingMessage]);
        $result = $this->fetchConversationListFor($connectedUser, [$conversation]);

        self::assertSame(1, $result['items'][0]['unreadMessagesCount']);
    }

    public function testGetByUserUnreadMessagesCountWhenLastReadMessageAtIsSet(): void
    {
        $connectedUser = $this->mockUser();

        $sender = $this->createMock(User::class);
        $sender->method('getId')->willReturn('sender-id');

        $readMessage = $this->createMock(ChatMessage::class);
        $readMessage->method('getId')->willReturn('read-message-id');
        $readMessage->method('getContent')->willReturn('already read');
        $readMessage->method('getSender')->willReturn($sender);
        $readMessage->method('getDeletedAt')->willReturn(null);
        $readMessage->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'));

        $messageAtBoundary = $this->createMock(ChatMessage::class);
        $messageAtBoundary->method('getId')->willReturn('boundary-message-id');
        $messageAtBoundary->method('getContent')->willReturn('at boundary');
        $messageAtBoundary->method('getSender')->willReturn($sender);
        $messageAtBoundary->method('getDeletedAt')->willReturn(null);
        $messageAtBoundary->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'));

        $unreadMessage = $this->createMock(ChatMessage::class);
        $unreadMessage->method('getId')->willReturn('unread-message-id');
        $unreadMessage->method('getContent')->willReturn('new message');
        $unreadMessage->method('getSender')->willReturn($sender);
        $unreadMessage->method('getDeletedAt')->willReturn(null);
        $unreadMessage->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T12:00:00+00:00'));

        $participant = $this->createMock(ConversationParticipant::class);
        $participant->method('getUser')->willReturn($connectedUser);
        $participant->method('getId')->willReturn('participant-id');
        $participant->method('getLastReadMessageAt')->willReturn(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'));

        $conversation = $this->createConversation([$participant], [$readMessage, $messageAtBoundary, $unreadMessage]);
        $result = $this->fetchConversationListFor($connectedUser, [$conversation]);

        self::assertSame(1, $result['items'][0]['unreadMessagesCount']);
    }

    /**
     * @param array<int, ConversationParticipant> $participants
     * @param array<int, ChatMessage> $messages
     */
    private function createConversation(array $participants, array $messages): Conversation
    {
        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn('conversation-id');
        $conversation->method('getChat')->willReturn(null);
        $conversation->method('getType')->willReturn(ConversationType::DIRECT);
        $conversation->method('getTitle')->willReturn('Conversation');
        $conversation->method('getParticipants')->willReturn(new ArrayCollection($participants));
        $conversation->method('getMessages')->willReturn(new ArrayCollection($messages));
        $conversation->method('getLastMessageAt')->willReturn(null);
        $conversation->method('getArchivedAt')->willReturn(null);
        $conversation->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'));

        return $conversation;
    }

    /**
     * @param array<int, Conversation> $conversations
     *
     * @return array<string, mixed>
     */
    private function fetchConversationListFor(User $connectedUser, array $conversations): array
    {
        $repo = $this->createMock(ConversationRepositoryInterface::class);
        $repo->expects(self::once())->method('findByUser')->willReturn($conversations);
        $repo->expects(self::once())->method('countByUser')->willReturn(count($conversations));

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::never())->method('search');

        $cacheKeyConventionService = $this->createMock(CacheKeyConventionService::class);
        $cacheKeyConventionService->expects(self::once())
            ->method('buildPrivateConversationKey')
            ->willReturn('cache-key');

        $item = $this->createMock(ItemInterface::class);
        $item->expects(self::once())->method('expiresAfter')->with(120);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $service = new ConversationListService($repo, $cache, $elastic, $cacheKeyConventionService);

        return $service->getByUser($connectedUser, [], 1, 20);
    }

    private function mockUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-id');
        $user->method('getUsername')->willReturn('john-doe');

        return $user;
    }
}
