<?php

declare(strict_types=1);

namespace App\Game\Application\Service;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\Entity\GameStatistic;
use App\Game\Domain\ValueObject\StatisticKey;
use App\Game\Domain\ValueObject\StatisticValue;
use App\User\Domain\Entity\User;

use function array_filter;
use function array_map;
use function array_sum;
use function array_values;
use function count;
use function max;

readonly class GameStatisticService
{
    public function __construct(private ScoreCalculatorService $scoreCalculatorService)
    {
    }

    /**
     * @param list<GameSession> $sessions
     * @param list<GameScore>   $scores
     *
     * @return list<GameStatistic>
     */
    public function buildForGame(Game $game, array $sessions, array $scores, ?User $user = null): array
    {
        $scoreValues = array_values(array_map(static fn (GameScore $score): int => $score->getValue(), $scores));
        $totalGames = count($sessions);
        $wins = count(array_filter($sessions, static fn (GameSession $session): bool => ($session->getContext()['is_win'] ?? false) === true));

        $best = $scoreValues === [] ? 0 : max($scoreValues);
        $average = $scoreValues === [] ? 0.0 : (array_sum($scoreValues) / count($scoreValues));
        $winRate = $totalGames === 0 ? 0.0 : (($wins / $totalGames) * 100);

        $baseStats = [
            'winrate' => new StatisticValue($winRate),
            'average_score' => new StatisticValue($average),
            'best_score' => new StatisticValue((float)$best),
            'streak' => new StatisticValue((float)$this->computeWinStreak($sessions)),
        ];

        $strategy = $this->scoreCalculatorService->resolveStrategyForGame($game);
        $strategyStats = $strategy->calculateStatistics($game, $sessions, $scores);

        $statistics = [];
        foreach ([...$baseStats, ...$strategyStats] as $key => $value) {
            $statistics[] = (new GameStatistic())
                ->setGame($game)
                ->setUser($user)
                ->setKey((new StatisticKey($key))->toString())
                ->setValue($value->toFloat());
        }

        return $statistics;
    }

    /**
     * @param list<GameSession> $sessions
     */
    private function computeWinStreak(array $sessions): int
    {
        $current = 0;
        $best = 0;

        foreach ($sessions as $session) {
            if (($session->getContext()['is_win'] ?? false) === true) {
                ++$current;
                $best = max($best, $current);

                continue;
            }

            $current = 0;
        }

        return $best;
    }
}
