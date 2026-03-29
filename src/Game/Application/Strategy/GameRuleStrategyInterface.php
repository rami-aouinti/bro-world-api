<?php

declare(strict_types=1);

namespace App\Game\Application\Strategy;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;
use App\User\Domain\Entity\User;

interface GameRuleStrategyInterface
{
    public function supports(Game $game): bool;

    public function computeScore(GameSession $session): GameScore;

    /**
     * @return array<string,float|int>
     */
    public function computeStats(Game $game, ?User $user): array;
}
