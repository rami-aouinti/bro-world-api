<?php

declare(strict_types=1);

namespace App\Game\Application\Service;

use App\Game\Application\Strategy\GameRuleStrategyInterface;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\ValueObject\ScoreValue;
use RuntimeException;

readonly class ScoreCalculatorService
{
    /**
     * @param iterable<GameRuleStrategyInterface> $strategies
     */
    public function __construct(private iterable $strategies)
    {
    }

    /**
     * @param array<string,mixed> $context
     */
    public function calculate(GameSession $session, array $context = []): ScoreValue
    {
        $strategy = $this->resolveStrategy($session);

        return $strategy->calculateScore($session, $context);
    }

    public function resolveStrategy(GameSession $session): GameRuleStrategyInterface
    {
        $game = $session->getGame();
        if (null === $game) {
            throw new RuntimeException('Unable to resolve rule strategy without an associated game.');
        }

        return $this->resolveStrategyForGame($game);
    }

    public function resolveStrategyForGame(Game $game): GameRuleStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($game)) {
                return $strategy;
            }
        }

        throw new RuntimeException('No rule strategy found for game category: ' . ($game->getCategory()?->getKey() ?? 'unknown'));
    }
}
