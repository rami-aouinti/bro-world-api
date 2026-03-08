<?php

declare(strict_types=1);

namespace App\Notification\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'notification')]
class Notification implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private string $title = '';

    #[ORM\Column(name: 'description', type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(name: 'type', type: Types::STRING, length: 100)]
    private string $type = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'from_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $from = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    public function __construct() { $this->id = $this->createUuid(); }
    #[Override] public function getId(): string { return $this->id->toString(); }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getFrom(): ?User { return $this->from; }
    public function setFrom(?User $from): self { $this->from = $from; return $this; }
    public function getRecipient(): User { return $this->recipient; }
    public function setRecipient(User $recipient): self { $this->recipient = $recipient; return $this; }
}
