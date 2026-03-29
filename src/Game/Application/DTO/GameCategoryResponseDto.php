<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

use App\Game\Domain\Entity\GameCategory;

final readonly class GameCategoryResponseDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $key,
        public string $description,
    ) {
    }

    public static function fromEntity(GameCategory $category): self
    {
        return new self(
            id: $category->getId(),
            name: $category->getName(),
            key: $category->getKey(),
            description: $category->getDescription(),
        );
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'description' => $this->description,
        ];
    }
}
