<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Recruit\Domain\Enum\OfferStatus;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_offer_status_history')]
#[ORM\Index(name: 'idx_recruit_offer_status_history_offer_created_at', columns: ['offer_id', 'created_at'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class OfferStatusHistory implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Offer::class)]
    #[ORM\JoinColumn(name: 'offer_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Offer $offer;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(name: 'action', type: Types::STRING, length: 50)]
    private string $action;

    #[ORM\Column(name: 'from_status', type: Types::STRING, length: 25, enumType: OfferStatus::class)]
    private OfferStatus $fromStatus;

    #[ORM\Column(name: 'to_status', type: Types::STRING, length: 25, enumType: OfferStatus::class)]
    private OfferStatus $toStatus;

    #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getOffer(): Offer
    {
        return $this->offer;
    }

    public function setOffer(Offer $offer): self
    {
        $this->offer = $offer;

        return $this;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getFromStatus(): OfferStatus
    {
        return $this->fromStatus;
    }

    public function setFromStatus(OfferStatus $fromStatus): self
    {
        $this->fromStatus = $fromStatus;

        return $this;
    }

    public function getToStatus(): OfferStatus
    {
        return $this->toStatus;
    }

    public function setToStatus(OfferStatus $toStatus): self
    {
        $this->toStatus = $toStatus;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
