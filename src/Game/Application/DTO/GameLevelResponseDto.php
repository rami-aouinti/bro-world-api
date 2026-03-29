<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

use App\Game\Domain\Entity\GameLevelOption;

final readonly class GameLevelResponseDto
{
    public function __construct(
        public string $id,
        public string $value,
        public string $label,
        public string $description,
    ) {
    }

    public static function fromEntity(GameLevelOption $level): self
    {
        return new self(
            id: $level->getId(),
            value: $level->getValue(),
            label: $level->getLabel(),
            description: $level->getDescription(),
        );
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'label' => $this->label,
            'description' => $this->description,
        ];
    }
}
