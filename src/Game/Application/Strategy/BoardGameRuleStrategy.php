<?php

declare(strict_types=1);

namespace App\Game\Application\Strategy;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\ValueObject\ScoreValue;
use App\Game\Domain\ValueObject\StatisticValue;

use function array_sum;
use function count;
use function is_array;
use function is_numeric;

final readonly class BoardGameRuleStrategy implements GameRuleStrategyInterface
{
    public function supports(Game $game): bool
    {
        return $game->getCategory()?->getKey() === 'board';
    }

    public function calculateScore(GameSession $session, array $context = []): ScoreValue
    {
        $objectives = $context['objectives'] ?? [];
        $turnPenalty = $context['turn_penalty'] ?? 0;

        $objectivePoints = is_array($objectives)
            ? (int)array_sum(array_map(static fn (mixed $value): int => is_numeric($value) ? (int)$value : 0, $objectives))
            : 0;

        $penalty = is_numeric($turnPenalty) ? (int)$turnPenalty : 0;

        return new ScoreValue($objectivePoints - $penalty > 0 ? $objectivePoints - $penalty : 0);
    }

    public function calculateStatistics(Game $game, array $sessions, array $scores): array
    {
        $players = [];
        foreach ($sessions as $session) {
            $user = $session->getUser();
            if (null === $user) {
                continue;
            }

            $players[$user->getId()] = true;
        }

        return [
            'matches' => new StatisticValue((float)count($sessions)),
            'unique_players' => new StatisticValue((float)count($players)),
        ];
    }
}
