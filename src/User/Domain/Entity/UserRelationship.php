<?php

declare(strict_types=1);

namespace App\User\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Enum\UserRelationshipStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

use function sprintf;

#[ORM\Entity]
#[ORM\Table(name: 'user_relationship')]
#[ORM\UniqueConstraint(name: 'uq_user_relationship_key', columns: ['relationship_key'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class UserRelationship implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true, nullable: false)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'requester_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $requester;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'addressee_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $addressee;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, enumType: UserRelationshipStatus::class)]
    private UserRelationshipStatus $status = UserRelationshipStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'blocked_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $blockedBy = null;

    #[ORM\Column(name: 'relationship_key', type: Types::STRING, length: 73, nullable: false)]
    private string $relationshipKey = '';

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
        $this->refreshRelationshipKey();

        return $this;
    }

    public function getAddressee(): User
    {
        return $this->addressee;
    }

    public function setAddressee(User $addressee): self
    {
        $this->addressee = $addressee;
        $this->refreshRelationshipKey();

        return $this;
    }

    public function getStatus(): UserRelationshipStatus
    {
        return $this->status;
    }

    public function setStatus(UserRelationshipStatus $status): self
    {
        $this->status = $status;

        if ($status !== UserRelationshipStatus::BLOCKED) {
            $this->blockedBy = null;
        }

        return $this;
    }

    public function getBlockedBy(): ?User
    {
        return $this->blockedBy;
    }

    public function setBlockedBy(?User $blockedBy): self
    {
        $this->blockedBy = $blockedBy;

        return $this;
    }

    public function getRelationshipKey(): string
    {
        return $this->relationshipKey;
    }

    private function refreshRelationshipKey(): void
    {
        if (!isset($this->requester, $this->addressee)) {
            return;
        }

        $requesterId = $this->requester->getId();
        $addresseeId = $this->addressee->getId();

        if ($requesterId <= $addresseeId) {
            $this->relationshipKey = sprintf('%s:%s', $requesterId, $addresseeId);

            return;
        }

        $this->relationshipKey = sprintf('%s:%s', $addresseeId, $requesterId);
    }
}
