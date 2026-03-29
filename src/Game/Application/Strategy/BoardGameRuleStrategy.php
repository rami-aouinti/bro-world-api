<?php

declare(strict_types=1);

namespace App\Game\Application\Strategy;

use App\Game\Infrastructure\Repository\GameSessionRepository;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;
use App\User\Domain\Entity\User;
use DateTimeImmutable;

use function array_map;
use function array_sum;
use function count;
use function is_array;
use function is_numeric;

final readonly class BoardGameRuleStrategy implements GameRuleStrategyInterface
{
    public function __construct(private GameSessionRepository $sessionRepository)
    {
    }

    public function supports(Game $game): bool
    {
        return $game->getCategory()?->getKey() === 'board';
    }

    public function computeScore(GameSession $session): GameScore
    {
        $context = $session->getContext();
        $objectives = $context['objectives'] ?? [];
        $turnPenalty = $context['turn_penalty'] ?? 0;

        $objectivePoints = is_array($objectives)
            ? (int) array_sum(array_map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0, $objectives))
            : 0;

        $penalty = is_numeric($turnPenalty) ? (int) $turnPenalty : 0;
        $scoreValue = max($objectivePoints - $penalty, 0);

        return (new GameScore())
            ->setSession($session)
            ->setValue($scoreValue)
            ->setBreakdown($context)
            ->setCalculatedAt(new DateTimeImmutable());
    }

    public function computeStats(Game $game, ?User $user): array
    {
        $sessions = $this->sessionRepository->findCompletedByGameAndUser($game, $user);
        $players = [];

        foreach ($sessions as $session) {
            $player = $session->getUser();
            if (null === $player) {
                continue;
            }

            $players[$player->getId()] = true;
        }

        return [
            'matches' => count($sessions),
            'unique_players' => count($players),
        ];
    }
}
