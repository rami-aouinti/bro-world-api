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

final readonly class CardGameRuleStrategy implements GameRuleStrategyInterface
{
    public function supports(Game $game): bool
    {
        return $game->getCategory()?->getKey() === 'card';
    }

    public function calculateScore(GameSession $session, array $context = []): ScoreValue
    {
        $cards = $context['cards'] ?? [];
        $bonus = $context['bonus'] ?? 0;

        $cardPoints = is_array($cards)
            ? (int)array_sum(array_map(static fn (mixed $value): int => is_numeric($value) ? (int)$value : 0, $cards))
            : 0;

        $bonusPoints = is_numeric($bonus) ? (int)$bonus : 0;

        return new ScoreValue($cardPoints + $bonusPoints);
    }

    public function calculateStatistics(Game $game, array $sessions, array $scores): array
    {
        $rounds = count($sessions);
        $wins = 0;
        foreach ($sessions as $session) {
            if (($session->getContext()['is_win'] ?? false) === true) {
                ++$wins;
            }
        }

        return [
            'rounds' => new StatisticValue((float)$rounds),
            'wins' => new StatisticValue((float)$wins),
        ];
    }
}
