<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Application\Service;

use App\Chat\Application\Service\ChatAccessResolverService;
use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\ChatMessageReaction;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ChatMessageReactionRepository;
use App\Chat\Infrastructure\Repository\ChatMessageRepository;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ChatAccessResolverServiceTest extends TestCase
{
    public function testResolveParticipantConversationReturnsConversation(): void
    {
        $user = $this->createMock(User::class);
        $conversation = $this->createMock(Conversation::class);
        $participant = $this->createMock(ConversationParticipant::class);

        $conversationRepository = $this->createMock(ConversationRepository::class);
        $participantRepository = $this->createMock(ConversationParticipantRepository::class);
        $messageRepository = $this->createMock(ChatMessageRepository::class);
        $reactionRepository = $this->createMock(ChatMessageReactionRepository::class);

        $conversationRepository->method('find')->with('conversation-id')->willReturn($conversation);
        $participantRepository->method('findOneByConversationAndUser')->with($conversation, $user)->willReturn($participant);

        $service = new ChatAccessResolverService($conversationRepository, $participantRepository, $messageRepository, $reactionRepository);

        self::assertSame($conversation, $service->resolveParticipantConversation('conversation-id', $user));
    }

    public function testResolveAccessibleMessageThrowsNotFoundWhenUserIsNotConversationParticipant(): void
    {
        $user = $this->createMock(User::class);
        $message = $this->createMock(ChatMessage::class);
        $conversation = $this->createMock(Conversation::class);

        $conversationRepository = $this->createMock(ConversationRepository::class);
        $participantRepository = $this->createMock(ConversationParticipantRepository::class);
        $messageRepository = $this->createMock(ChatMessageRepository::class);
        $reactionRepository = $this->createMock(ChatMessageReactionRepository::class);

        $message->method('getConversation')->willReturn($conversation);
        $messageRepository->method('find')->with('message-id')->willReturn($message);
        $participantRepository->method('findOneByConversationAndUser')->with($conversation, $user)->willReturn(null);

        $service = new ChatAccessResolverService($conversationRepository, $participantRepository, $messageRepository, $reactionRepository);

        try {
            $service->resolveAccessibleMessage('message-id', $user);
            self::fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame('Message not found.', $exception->getMessage());
        }
    }

    public function testResolveOwnReactionThrowsNotFoundWhenReactionDoesNotBelongToUser(): void
    {
        $user = $this->createMock(User::class);
        $owner = $this->createMock(User::class);
        $reaction = $this->createMock(ChatMessageReaction::class);

        $conversationRepository = $this->createMock(ConversationRepository::class);
        $participantRepository = $this->createMock(ConversationParticipantRepository::class);
        $messageRepository = $this->createMock(ChatMessageRepository::class);
        $reactionRepository = $this->createMock(ChatMessageReactionRepository::class);

        $user->method('getId')->willReturn('user-id');
        $owner->method('getId')->willReturn('owner-id');
        $reaction->method('getUser')->willReturn($owner);
        $reactionRepository->method('find')->with('reaction-id')->willReturn($reaction);

        $service = new ChatAccessResolverService($conversationRepository, $participantRepository, $messageRepository, $reactionRepository);

        try {
            $service->resolveOwnReaction('reaction-id', $user);
            self::fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame('Reaction not found.', $exception->getMessage());
        }
    }
}
