<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

use App\Game\Domain\Entity\GameScore;

final readonly class LeaderboardEntryResponseDto
{
    public function __construct(
        public string $scoreId,
        public string $sessionId,
        public ?string $userId,
        public int $score,
        public string $calculatedAt,
    ) {
    }

    public static function fromEntity(GameScore $gameScore): self
    {
        return new self(
            scoreId: $gameScore->getId(),
            sessionId: (string)$gameScore->getSession()?->getId(),
            userId: $gameScore->getSession()?->getUser()?->getId(),
            score: $gameScore->getValue(),
            calculatedAt: $gameScore->getCalculatedAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'scoreId' => $this->scoreId,
            'sessionId' => $this->sessionId,
            'userId' => $this->userId,
            'score' => $this->score,
            'calculatedAt' => $this->calculatedAt,
        ];
    }
}
