<?php

declare(strict_types=1);

namespace App\Chat\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'chat_message')]
#[ORM\Index(name: 'idx_chat_message_conversation_id', columns: ['conversation_id'])]
#[ORM\Index(name: 'idx_chat_message_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_chat_message_sender_id', columns: ['sender_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class ChatMessage implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $sender;

    #[ORM\Column(name: 'content', type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(name: 'is_read', type: Types::BOOLEAN, options: [
        'default' => false,
    ])]
    private bool $read = false;

    #[ORM\Column(name: 'read_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $readAt = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column(name: 'attachments', type: Types::JSON)]
    private array $attachments = [];

    /**
     * @var Collection<int, ChatMessageReaction>
     */
    #[ORM\OneToMany(targetEntity: ChatMessageReaction::class, mappedBy: 'message', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $reactions;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->reactions = new ArrayCollection();
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

    public function getSender(): User
    {
        return $this->sender;
    }

    public function setSender(User $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function setRead(bool $read): self
    {
        $this->read = $read;

        return $this;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?DateTimeImmutable $readAt): self
    {
        $this->readAt = $readAt;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     */
    public function setAttachments(array $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * @return Collection<int, ChatMessageReaction>
     */
    public function getReactions(): Collection
    {
        return $this->reactions;
    }

    public function addReaction(ChatMessageReaction $reaction): self
    {
        if (!$this->reactions->contains($reaction)) {
            $this->reactions->add($reaction);
            $reaction->setMessage($this);
        }

        return $this;
    }
}
