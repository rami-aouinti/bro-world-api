<?php

declare(strict_types=1);

namespace App\Game\Application\Strategy;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\ValueObject\ScoreValue;
use App\Game\Domain\ValueObject\StatisticValue;

interface GameRuleStrategyInterface
{
    public function supports(Game $game): bool;

    /**
     * @param array<string,mixed> $context
     */
    public function calculateScore(GameSession $session, array $context = []): ScoreValue;

    /**
     * @param list<GameSession> $sessions
     * @param list<GameScore> $scores
     *
     * @return array<string,StatisticValue>
     */
    public function calculateStatistics(Game $game, array $sessions, array $scores): array;
}
