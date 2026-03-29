<?php

declare(strict_types=1);

namespace App\Game\Application\Service;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\Enum\GameStatus;
use App\User\Domain\Entity\User;
use DateTimeImmutable;

readonly class GameSessionService
{
    public function __construct(private ScoreCalculatorService $scoreCalculatorService)
    {
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

        $scoreValue = $this->scoreCalculatorService->calculate($session, $context);

        return (new GameScore())
            ->setSession($session)
            ->setValue($scoreValue->toInt())
            ->setBreakdown($context)
            ->setCalculatedAt(new DateTimeImmutable());
    }
}
