<?php

declare(strict_types=1);

namespace App\Chat\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'conversation')]
#[ORM\UniqueConstraint(name: 'uq_conversation_chat_application_slug', columns: ['chat_id', 'application_slug'])]
#[ORM\Index(name: 'idx_conversation_chat_id', columns: ['chat_id'])]
#[ORM\Index(name: 'idx_conversation_application_slug', columns: ['application_slug'])]
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

    #[ORM\Column(name: 'application_slug', type: 'string', length: 100)]
    private string $applicationSlug;

    public function __construct()
    {
        $this->id = $this->createUuid();
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

    public function getApplicationSlug(): string
    {
        return $this->applicationSlug;
    }

    public function setApplicationSlug(string $applicationSlug): self
    {
        $this->applicationSlug = $applicationSlug;

        return $this;
    }
}
