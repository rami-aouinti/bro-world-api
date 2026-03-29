<?php

declare(strict_types=1);

namespace App\Game\Application\Service;

use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;

final readonly class ScoreCalculatorService
{
    public function __construct(private GameRuleRegistry $gameRuleRegistry)
    {
    }

    public function calculate(GameSession $session): GameScore
    {
        $game = $session->getGame();
        if (null === $game) {
            throw new \RuntimeException('Unable to resolve rule strategy without an associated game.');
        }

        return $this->gameRuleRegistry->resolve($game)->computeScore($session);
    }
}
