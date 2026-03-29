<?php

declare(strict_types=1);

namespace App\Game\Application\Service;

use App\Game\Application\Strategy\GameRuleStrategyInterface;
use App\Game\Domain\Entity\Game;
use RuntimeException;

final readonly class GameRuleRegistry
{
    /**
     * @param iterable<GameRuleStrategyInterface> $strategies
     */
    public function __construct(private iterable $strategies)
    {
    }

    public function resolve(Game $game): GameRuleStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($game)) {
                return $strategy;
            }
        }

        $category = $game->getCategory()?->getKey() ?? 'unknown';
        $type = (string) ($game->getMetadata()['type'] ?? 'unknown');

        throw new RuntimeException(sprintf('No rule strategy found for game category "%s" and type "%s".', $category, $type));
    }
}
