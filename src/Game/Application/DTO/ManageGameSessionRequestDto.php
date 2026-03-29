<?php

declare(strict_types=1);

namespace App\Game\Application\DTO;

final readonly class ManageGameSessionRequestDto
{
    /**
     * @param array<string,mixed> $context
     */
    public function __construct(
        public string $action,
        public ?string $sessionId,
        public array $context = [],
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            action: strtolower(trim((string)($payload['action'] ?? 'start'))),
            sessionId: isset($payload['sessionId']) ? trim((string)$payload['sessionId']) : null,
            context: (array)($payload['context'] ?? []),
        );
    }

    public function isStart(): bool
    {
        return $this->action === 'start';
    }

    public function isComplete(): bool
    {
        return $this->action === 'complete';
    }
}
