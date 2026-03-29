<?php

declare(strict_types=1);

namespace App\Game\Application\Service;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\Enum\GameStatus;
use App\Game\Infrastructure\Repository\GameScoreRepository;
use App\Game\Infrastructure\Repository\GameSessionRepository;
use App\User\Domain\Entity\User;
use DateTimeImmutable;

final readonly class GameSessionService
{
    public function __construct(
        private GameRuleRegistry $gameRuleRegistry,
        private GameSessionRepository $gameSessionRepository,
        private GameScoreRepository $gameScoreRepository,
        private GameStatisticService $gameStatisticService,
    ) {
    }

    /**
     * @param array<string,mixed> $context
     */
    public function start(Game $game, User $user, array $context = []): GameSession
    {
        return (new GameSession())
            ->setGame($game)
            ->setUser($user)
            ->setStatus(GameStatus::ACTIVE)
            ->setStartedAt(new DateTimeImmutable())
            ->setContext($context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function complete(GameSession $session, array $context = []): GameScore
    {
        $session
            ->setStatus(GameStatus::COMPLETED)
            ->setEndedAt(new DateTimeImmutable())
            ->setContext($context);

        $game = $session->getGame();
        if (null === $game) {
            throw new \RuntimeException('Unable to compute score without a related game.');
        }

        $strategy = $this->gameRuleRegistry->resolve($game);
        $score = $strategy->computeScore($session);

        $this->gameSessionRepository->save($session, false);
        $this->gameScoreRepository->save($score, true);
        $this->gameStatisticService->refreshForGame($game, $session->getUser());

        return $score;
    }
}
