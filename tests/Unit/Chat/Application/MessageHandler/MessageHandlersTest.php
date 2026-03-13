<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Application\MessageHandler;

use App\Chat\Application\Message\CreateMessageCommand;
use App\Chat\Application\Message\MarkConversationMessagesAsReadCommand;
use App\Chat\Application\Message\PatchMessageCommand;
use App\Chat\Application\MessageHandler\CreateMessageCommandHandler;
use App\Chat\Application\MessageHandler\MarkConversationMessagesAsReadCommandHandler;
use App\Chat\Application\MessageHandler\PatchMessageCommandHandler;
use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ChatMessageRepository;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\General\Application\Service\MercurePublisher;
use App\General\Domain\Rest\UuidHelper;
use App\Tests\Utils\PhpUnitUtil;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MessageHandlersTest extends TestCase
{
    public function testCreateMessageHandlerRejectsNonParticipant(): void
    {
        $conversationRepo = $this->createMock(ConversationRepository::class);
        $participantRepo = $this->createMock(ConversationParticipantRepository::class);
        $userRepo = $this->createMock(UserRepository::class);
        $messageRepo = $this->createMock(ChatMessageRepository::class);
        $cache = $this->createMock(CacheInvalidationService::class);
        $mercure = $this->createMock(MercurePublisher::class);

        $actor = $this->makeUser('20000000-0000-1000-8000-000000000006');
        $conversation = $this->makeConversation('91000000-0000-1000-8000-000000000003');

        $userRepo->method('find')->willReturn($actor);
        $conversationRepo->method('find')->willReturn($conversation);
        $participantRepo->method('findOneByConversationAndUser')->willReturn(null);

        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $func) => $func());
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $messageRepo->method('getEntityManager')->willReturn($em);

        $cache->expects(self::never())->method('invalidateConversationCaches');

        $handler = new CreateMessageCommandHandler($conversationRepo, $participantRepo, $userRepo, $messageRepo, $cache, $mercure);

        $this->expectException(HttpException::class);
        $handler(new CreateMessageCommand('op', $actor->getId(), $conversation->getId(), 'hello'));
    }

    public function testPatchMessageHandlerUpdatesReadTransitionsAndInvalidatesCache(): void
    {
        $messageRepo = $this->createMock(ChatMessageRepository::class);
        $cache = $this->createMock(CacheInvalidationService::class);

        $sender = $this->makeUser('20000000-0000-1000-8000-000000000006');
        $conversation = $this->makeConversation('91000000-0000-1000-8000-000000000003');
        $message = (new ChatMessage())
            ->setConversation($conversation)
            ->setSender($sender)
            ->setContent('draft')
            ->setRead(false);
        PhpUnitUtil::setProperty('id', UuidHelper::fromString('91000000-0000-1000-8000-000000000010'), $message);

        $messageRepo->method('find')->willReturn($message);
        $messageRepo->expects(self::exactly(2))->method('save');

        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $func) => $func());
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $messageRepo->method('getEntityManager')->willReturn($em);

        $cache->expects(self::exactly(2))->method('invalidateConversationCaches')->with($conversation->getChat()->getId(), $sender->getId());

        $handler = new PatchMessageCommandHandler($messageRepo, $cache);

        $handler(new PatchMessageCommand('op-1', $sender->getId(), $message->getId(), null, true));
        self::assertTrue($message->isRead());
        self::assertNotNull($message->getReadAt());

        $handler(new PatchMessageCommand('op-2', $sender->getId(), $message->getId(), null, false));
        self::assertFalse($message->isRead());
        self::assertNull($message->getReadAt());
    }

    public function testMarkConversationMessagesAsReadUpdatesParticipantReadPointerAndInvalidatesCache(): void
    {
        $conversationRepo = $this->createMock(ConversationRepository::class);
        $participantRepo = $this->createMock(ConversationParticipantRepository::class);
        $cache = $this->createMock(CacheInvalidationService::class);

        $actor = $this->makeUser('20000000-0000-1000-8000-000000000006');
        $conversation = $this->makeConversation('91000000-0000-1000-8000-000000000003');
        $participant = (new ConversationParticipant())->setConversation($conversation)->setUser($actor);

        $conversationRepo->method('find')->willReturn($conversation);
        $participantRepo->method('findOneBy')->willReturn($participant);
        $participantRepo->expects(self::exactly(2))->method('save');

        $cache->expects(self::exactly(2))->method('invalidateConversationCaches')->with($conversation->getChat()->getId(), $actor->getId());

        $handler = new MarkConversationMessagesAsReadCommandHandler($conversationRepo, $participantRepo, $cache);

        $handler(new MarkConversationMessagesAsReadCommand('op-1', $actor->getId(), $conversation->getId()));
        $firstReadAt = $participant->getLastReadMessageAt();
        self::assertNotNull($firstReadAt);

        $handler(new MarkConversationMessagesAsReadCommand('op-2', $actor->getId(), $conversation->getId()));
        self::assertNotNull($participant->getLastReadMessageAt());
    }

    private function makeConversation(string $id): Conversation
    {
        $chat = new Chat();
        PhpUnitUtil::setProperty('id', UuidHelper::fromString('91000000-0000-1000-8000-000000000001'), $chat);

        $conversation = (new Conversation())->setChat($chat);
        PhpUnitUtil::setProperty('id', UuidHelper::fromString($id), $conversation);

        return $conversation;
    }

    private function makeUser(string $id): User
    {
        $user = (new User())
            ->setUsername('john-root')
            ->setFirstName('John')
            ->setLastName('Root')
            ->setEmail('john.root@test.com')
            ->setPlainPassword('password-root');
        PhpUnitUtil::setProperty('id', UuidHelper::fromString($id), $user);

        return $user;
    }
}
