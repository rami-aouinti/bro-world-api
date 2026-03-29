<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;

final readonly class GameSessionResponseDto
{
    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed>|null $score
     */
    public function __construct(
        public string $sessionId,
        public string $status,
        public string $startedAt,
        public ?string $endedAt,
        public array $context,
        public ?array $score,
    ) {
    }

    public static function fromEntity(GameSession $session, ?GameScore $score = null): self
    {
        return new self(
            sessionId: $session->getId(),
            status: $session->getStatus()->value,
            startedAt: $session->getStartedAt()->format(DATE_ATOM),
            endedAt: $session->getEndedAt()?->format(DATE_ATOM),
            context: $session->getContext(),
            score: $score !== null ? [
                'id' => $score->getId(),
                'value' => $score->getValue(),
                'breakdown' => $score->getBreakdown(),
                'calculatedAt' => $score->getCalculatedAt()->format(DATE_ATOM),
            ] : null,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'sessionId' => $this->sessionId,
            'status' => $this->status,
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
            'context' => $this->context,
            'score' => $this->score,
        ];
    }
}
