<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

use App\Game\Domain\Entity\GameStatistic;

final readonly class GameStatisticResponseDto
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $key,
        public float $value,
        public array $metadata,
        public string $recordedAt,
    ) {
    }

    public static function fromEntity(GameStatistic $statistic): self
    {
        return new self(
            key: $statistic->getKey(),
            value: $statistic->getValue(),
            metadata: $statistic->getMetadata(),
            recordedAt: $statistic->getRecordedAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'metadata' => $this->metadata,
            'recordedAt' => $this->recordedAt,
        ];
    }
}
