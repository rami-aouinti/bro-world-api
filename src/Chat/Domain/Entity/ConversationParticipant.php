<?php

declare(strict_types=1);

namespace App\Chat\Domain\Entity;

use App\Chat\Domain\Enum\ConversationParticipantRole;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'chat_conversation_participant')]
#[ORM\UniqueConstraint(name: 'uq_conversation_participant_conversation_user', columns: ['conversation_id', 'user_id'])]
#[ORM\Index(name: 'idx_conversation_participant_conversation_id', columns: ['conversation_id'])]
#[ORM\Index(name: 'idx_conversation_participant_user_id', columns: ['user_id'])]
#[ORM\Index(name: 'idx_chat_conversation_participant_user_conversation', columns: ['user_id', 'conversation_id'])]
#[ORM\Index(name: 'idx_conversation_participant_conversation_user_last_read', columns: ['conversation_id', 'user_id', 'last_read_message_at'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class ConversationParticipant implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'last_read_message_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastReadMessageAt = null;

    #[ORM\Column(name: 'muted_until', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $mutedUntil = null;

    #[ORM\Column(name: 'role', type: Types::STRING, length: 25, enumType: ConversationParticipantRole::class, options: [
        'default' => ConversationParticipantRole::MEMBER->value,
    ])]
    private ConversationParticipantRole $role = ConversationParticipantRole::MEMBER;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function setConversation(Conversation $conversation): self
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getLastReadMessageAt(): ?DateTimeImmutable
    {
        return $this->lastReadMessageAt;
    }

    public function setLastReadMessageAt(?DateTimeImmutable $lastReadMessageAt): self
    {
        $this->lastReadMessageAt = $lastReadMessageAt;

        return $this;
    }

    public function getMutedUntil(): ?DateTimeImmutable
    {
        return $this->mutedUntil;
    }

    public function setMutedUntil(?DateTimeImmutable $mutedUntil): self
    {
        $this->mutedUntil = $mutedUntil;

        return $this;
    }

    public function getRole(): ConversationParticipantRole
    {
        return $this->role;
    }

    public function setRole(ConversationParticipantRole $role): self
    {
        $this->role = $role;

        return $this;
    }
}
