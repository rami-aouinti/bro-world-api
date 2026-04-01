<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

use App\Game\Domain\Entity\GameCategory;

final readonly class GameCategoryResponseDto
{
    public function __construct(
        public string $id,
        public string $nameKey,
        public string $descriptionKey,
        public ?string $img,
        public ?string $icon,
    ) {
    }

    public static function fromEntity(GameCategory $category): self
    {
        return new self(
            id: $category->getKey(),
            nameKey: $category->getNameKey(),
            descriptionKey: $category->getDescriptionKey() ?? '',
            img: $category->getImg(),
            icon: $category->getIcon(),
        );
    }

    /**
     * @return array<string,string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nameKey' => $this->nameKey,
            'descriptionKey' => $this->descriptionKey,
            'img' => $this->img,
            'icon' => $this->icon,
        ];
    }
}
