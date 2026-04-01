<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

use App\Game\Domain\Entity\Game;

final readonly class GameResponseDto
{
    /**
     * @param array<int,string> $supportedModes
     * @param array<int,string> $tags
     * @param array<int,string> $features
     */
    public function __construct(
        public string $id,
        public string $nameKey,
        public string $descriptionKey,
        public ?string $img,
        public ?string $icon,
        public ?string $component,
        public array $supportedModes,
        public ?string $categoryKey,
        public ?string $subcategoryKey,
        public ?string $difficultyKey,
        public array $tags,
        public array $features,
        public string $level,
        public string $status,
    ) {
    }

    public static function fromEntity(Game $game): self
    {
        return new self(
            id: $game->getKey(),
            nameKey: $game->getNameKey(),
            descriptionKey: $game->getDescriptionKey() ?? '',
            img: $game->getImg(),
            icon: $game->getIcon(),
            component: $game->getComponent(),
            supportedModes: $game->getSupportedModes(),
            categoryKey: $game->getCategoryKey() ?? $game->getCategory()?->getKey(),
            subcategoryKey: $game->getSubcategoryKey() ?? $game->getSubCategory()?->getKey(),
            difficultyKey: $game->getDifficultyKey(),
            tags: $game->getTags(),
            features: $game->getFeatures(),
            level: $game->getLevel()->value,
            status: $game->getStatus()->value,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nameKey' => $this->nameKey,
            'descriptionKey' => $this->descriptionKey,
            'img' => $this->img,
            'icon' => $this->icon,
            'component' => $this->component,
            'supportedModes' => $this->supportedModes,
            'categoryKey' => $this->categoryKey,
            'subcategoryKey' => $this->subcategoryKey,
            'difficultyKey' => $this->difficultyKey,
            'tags' => $this->tags,
            'features' => $this->features,
            'level' => $this->level,
            'status' => $this->status,
        ];
    }
}
