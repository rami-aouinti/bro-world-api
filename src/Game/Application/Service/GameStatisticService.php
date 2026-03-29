<?php

declare(strict_types=1);

namespace App\Game\Application\Service;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameStatistic;
use App\Game\Domain\ValueObject\StatisticKey;
use App\Game\Domain\ValueObject\StatisticValue;
use App\Game\Infrastructure\Repository\GameScoreRepository;
use App\Game\Infrastructure\Repository\GameSessionRepository;
use App\Game\Infrastructure\Repository\GameStatisticRepository;
use App\Game\Domain\Enum\GameStatus;
use App\User\Domain\Entity\User;

use function array_filter;
use function array_map;
use function array_sum;
use function count;
use function max;

final readonly class GameStatisticService
{
    public function __construct(
        private GameRuleRegistry $gameRuleRegistry,
        private GameSessionRepository $gameSessionRepository,
        private GameScoreRepository $gameScoreRepository,
        private GameStatisticRepository $gameStatisticRepository,
    ) {
    }

    /**
     * @return list<GameStatistic>
     */
    public function refreshForGame(Game $game, ?User $user = null): array
    {
        $sessions = $this->gameSessionRepository->findCompletedByGameAndUser($game, $user);

        $scores = $this->gameScoreRepository
            ->createQueryBuilder('score')
            ->innerJoin('score.session', 'session')
            ->andWhere('session.game = :game')
            ->setParameter('game', $game)
            ->andWhere('session.status = :status')
            ->setParameter('status', GameStatus::COMPLETED->value);

        if (null !== $user) {
            $scores
                ->andWhere('session.user = :user')
                ->setParameter('user', $user);
        }

        /** @var list<GameScore> $scoreEntities */
        $scoreEntities = $scores->getQuery()->getResult();

        $scoreValues = array_map(static fn ($score): int => $score->getValue(), $scoreEntities);
        $totalGames = count($sessions);
        $wins = count(array_filter($sessions, static fn (GameSession $session): bool => ($session->getContext()['is_win'] ?? false) === true));

        $best = $scoreValues === [] ? 0 : max($scoreValues);
        $average = $scoreValues === [] ? 0.0 : (array_sum($scoreValues) / count($scoreValues));
        $winRate = $totalGames === 0 ? 0.0 : (($wins / $totalGames) * 100);

        $strategyStats = $this->gameRuleRegistry->resolve($game)->computeStats($game, $user);

        $statistics = [
            'winrate' => new StatisticValue($winRate),
            'average_score' => new StatisticValue($average),
            'best_score' => new StatisticValue((float) $best),
            'streak' => new StatisticValue((float) $this->computeWinStreak($sessions)),
        ];

        foreach ($strategyStats as $key => $value) {
            $statistics[$key] = new StatisticValue((float) $value);
        }

        $entities = [];
        foreach ($statistics as $key => $value) {
            $entities[] = (new GameStatistic())
                ->setGame($game)
                ->setUser($user)
                ->setKey((new StatisticKey($key))->toString())
                ->setValue($value->toFloat());
        }

        $this->gameStatisticRepository->replaceForGameAndUser($game, $user, ...$entities);

        return $entities;
    }

    /**
     * @return list<GameStatistic>
     */
    public function getForGame(Game $game, ?User $user = null): array
    {
        return $this->gameStatisticRepository->findByGameAndUser($game, $user);
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
