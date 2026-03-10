<?php

declare(strict_types=1);

namespace App\Chat\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'chat_conversation')]
#[ORM\Index(name: 'idx_conversation_chat_id', columns: ['chat_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Conversation implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Chat::class, inversedBy: 'conversations')]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Chat $chat;

    /**
     * @var Collection<int, ConversationParticipant>
     */
    #[ORM\OneToMany(targetEntity: ConversationParticipant::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participants;

    /**
     * @var Collection<int, ChatMessage>
     */
    #[ORM\OneToMany(targetEntity: ChatMessage::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getChat(): Chat
    {
        return $this->chat;
    }

    public function setChat(Chat $chat): self
    {
        $this->chat = $chat;

        return $this;
    }

    /**
     * @return Collection<int, ConversationParticipant>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(ConversationParticipant $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setConversation($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(ChatMessage $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }

        return $this;
    }
}
