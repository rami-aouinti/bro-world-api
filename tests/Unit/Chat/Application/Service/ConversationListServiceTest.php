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

    public function testGetByUserUnreadCountUsesConnectedParticipantReadPointerInMultiParticipantConversation(): void
    {
        $connectedUser = $this->mockUser();

        $otherUserOne = $this->createMock(User::class);
        $otherUserOne->method('getId')->willReturn('user-2');
        $otherUserOne->method('getFirstName')->willReturn('Alice');
        $otherUserOne->method('getLastName')->willReturn('Two');
        $otherUserOne->method('getPhoto')->willReturn(null);

        $otherUserTwo = $this->createMock(User::class);
        $otherUserTwo->method('getId')->willReturn('user-3');
        $otherUserTwo->method('getFirstName')->willReturn('Bob');
        $otherUserTwo->method('getLastName')->willReturn('Three');
        $otherUserTwo->method('getPhoto')->willReturn(null);

        $connectedParticipant = $this->createMock(ConversationParticipant::class);
        $connectedParticipant->method('getUser')->willReturn($connectedUser);
        $connectedParticipant->method('getId')->willReturn('participant-connected');
        $connectedParticipant->method('getLastReadMessageAt')->willReturn(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'));

        $participantWithNewerPointer = $this->createMock(ConversationParticipant::class);
        $participantWithNewerPointer->method('getUser')->willReturn($otherUserOne);
        $participantWithNewerPointer->method('getId')->willReturn('participant-other-1');
        $participantWithNewerPointer->method('getLastReadMessageAt')->willReturn(new \DateTimeImmutable('2024-01-01T13:00:00+00:00'));

        $participantWithOlderPointer = $this->createMock(ConversationParticipant::class);
        $participantWithOlderPointer->method('getUser')->willReturn($otherUserTwo);
        $participantWithOlderPointer->method('getId')->willReturn('participant-other-2');
        $participantWithOlderPointer->method('getLastReadMessageAt')->willReturn(new \DateTimeImmutable('2024-01-01T09:00:00+00:00'));

        $messageBeforePointer = $this->createMock(ChatMessage::class);
        $messageBeforePointer->method('getId')->willReturn('message-1');
        $messageBeforePointer->method('getContent')->willReturn('before pointer');
        $messageBeforePointer->method('getSender')->willReturn($otherUserOne);
        $messageBeforePointer->method('getDeletedAt')->willReturn(null);
        $messageBeforePointer->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'));

        $messageAfterPointer = $this->createMock(ChatMessage::class);
        $messageAfterPointer->method('getId')->willReturn('message-2');
        $messageAfterPointer->method('getContent')->willReturn('after pointer');
        $messageAfterPointer->method('getSender')->willReturn($otherUserTwo);
        $messageAfterPointer->method('getDeletedAt')->willReturn(null);
        $messageAfterPointer->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T12:00:00+00:00'));

        $messageFromConnectedUser = $this->createMock(ChatMessage::class);
        $messageFromConnectedUser->method('getId')->willReturn('message-3');
        $messageFromConnectedUser->method('getContent')->willReturn('own message');
        $messageFromConnectedUser->method('getSender')->willReturn($connectedUser);
        $messageFromConnectedUser->method('getDeletedAt')->willReturn(null);
        $messageFromConnectedUser->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01T12:30:00+00:00'));

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn('conversation-id');
        $conversation->method('getChat')->willReturn(null);
        $conversation->method('getType')->willReturn(ConversationType::GROUP);
        $conversation->method('getTitle')->willReturn('Group Conversation');
        $conversation->method('getParticipants')->willReturn(new ArrayCollection([$connectedParticipant, $participantWithNewerPointer, $participantWithOlderPointer]));
        $conversation->method('getMessages')->willReturn(new ArrayCollection([$messageBeforePointer, $messageAfterPointer, $messageFromConnectedUser]));
        $conversation->method('getLastMessageAt')->willReturn(new \DateTimeImmutable('2024-01-01T12:30:00+00:00'));
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

        self::assertSame(1, $result['items'][0]['unreadMessagesCount']);
    }

    private function mockUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-id');
        $user->method('getUsername')->willReturn('john-doe');

        return $user;
    }
}
