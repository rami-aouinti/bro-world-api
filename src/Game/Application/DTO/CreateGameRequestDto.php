<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

use App\Game\Domain\Enum\GameLevel;
use App\Game\Domain\Enum\GameStatus;

final readonly class CreateGameRequestDto
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $name,
        public string $categoryId,
        public GameLevel $level,
        public GameStatus $status,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: trim((string)($payload['name'] ?? '')),
            categoryId: trim((string)($payload['categoryId'] ?? '')),
            level: GameLevel::tryFrom((string)($payload['level'] ?? '')) ?? GameLevel::BEGINNER,
            status: GameStatus::tryFrom((string)($payload['status'] ?? '')) ?? GameStatus::ACTIVE,
            metadata: (array)($payload['metadata'] ?? []),
        );
    }
}
