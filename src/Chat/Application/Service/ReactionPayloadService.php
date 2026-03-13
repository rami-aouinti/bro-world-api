<?php

declare(strict_types=1);

namespace App\Chat\Application\Service;

use App\Chat\Domain\Enum\ChatReactionType;

readonly class ReactionPayloadService
{
    public function __construct(
        private ReactionTypeParser $reactionTypeParser,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function extractRequiredReaction(array $payload): ChatReactionType
    {
        return $this->reactionTypeParser->parse($payload['reaction'] ?? null);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function extractOptionalReaction(array $payload): ?ChatReactionType
    {
        if (!array_key_exists('reaction', $payload)) {
            return null;
        }

        return $this->reactionTypeParser->parse($payload['reaction']);
    }
}
