<?php

declare(strict_types=1);

namespace App\User\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Enum\FriendStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'user_friend_relation')]
#[ORM\UniqueConstraint(name: 'uq_user_friend_relation_requester_addressee', columns: ['requester_id', 'addressee_id'])]
#[ORM\Index(name: 'idx_user_friend_relation_requester_id', columns: ['requester_id'])]
#[ORM\Index(name: 'idx_user_friend_relation_addressee_id', columns: ['addressee_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class UserFriendRelation implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'requester_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $requester;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'addressee_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $addressee;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, nullable: false)]
    private string $status = FriendStatus::PENDING->value;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getRequester(): User
    {
        return $this->requester;
    }

    public function setRequester(User $requester): self
    {
        $this->requester = $requester;

        return $this;
    }

    public function getAddressee(): User
    {
        return $this->addressee;
    }

    public function setAddressee(User $addressee): self
    {
        $this->addressee = $addressee;

        return $this;
    }

    public function getStatus(): FriendStatus
    {
        return FriendStatus::from($this->status);
    }

    public function setStatus(FriendStatus $status): self
    {
        $this->status = $status->value;

        return $this;
    }
}
