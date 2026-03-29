<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

use App\Game\Domain\Entity\Game;

final readonly class GameResponseDto
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $categoryId,
        public string $categoryKey,
        public string $categoryName,
        public string $level,
        public string $status,
        public array $metadata,
    ) {
    }

    public static function fromEntity(Game $game): self
    {
        $category = $game->getCategory();

        return new self(
            id: $game->getId(),
            name: $game->getName(),
            categoryId: (string)$category?->getId(),
            categoryKey: (string)$category?->getKey(),
            categoryName: (string)$category?->getName(),
            level: $game->getLevel()->value,
            status: $game->getStatus()->value,
            metadata: $game->getMetadata(),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'categoryId' => $this->categoryId,
            'categoryKey' => $this->categoryKey,
            'categoryName' => $this->categoryName,
            'level' => $this->level,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}
