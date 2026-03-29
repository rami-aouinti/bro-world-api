<?php

declare(strict_types=1);

namespace App\Game\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'game_score', indexes: [
    new ORM\Index(name: 'idx_game_score_session_calculated_at', columns: ['session_id', 'calculated_at']),
    new ORM\Index(name: 'idx_game_score_session_value', columns: ['session_id', 'value']),
])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class GameScore implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: GameSession::class, inversedBy: 'scores')]
    #[ORM\JoinColumn(name: 'session_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?GameSession $session = null;

    #[ORM\Column(name: 'value', type: Types::INTEGER)]
    private int $value = 0;

    #[ORM\Column(name: 'breakdown', type: Types::JSON)]
    private array $breakdown = [];

    #[ORM\Column(name: 'calculated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $calculatedAt;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->calculatedAt = new DateTimeImmutable();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getSession(): ?GameSession
    {
        return $this->session;
    }

    public function setSession(?GameSession $session): self
    {
        $this->session = $session;

        return $this;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function getBreakdown(): array
    {
        return $this->breakdown;
    }

    /**
     * @param array<string,mixed> $breakdown
     */
    public function setBreakdown(array $breakdown): self
    {
        $this->breakdown = $breakdown;

        return $this;
    }

    public function getCalculatedAt(): DateTimeImmutable
    {
        return $this->calculatedAt;
    }

    public function setCalculatedAt(DateTimeImmutable $calculatedAt): self
    {
        $this->calculatedAt = $calculatedAt;

        return $this;
    }
}
