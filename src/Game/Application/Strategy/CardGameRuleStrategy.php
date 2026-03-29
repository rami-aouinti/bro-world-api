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

final readonly class CardGameRuleStrategy implements GameRuleStrategyInterface
{
    public function __construct(private GameSessionRepository $sessionRepository)
    {
    }

    public function supports(Game $game): bool
    {
        return $game->getCategory()?->getKey() === 'card';
    }

    public function computeScore(GameSession $session): GameScore
    {
        $context = $session->getContext();
        $cards = $context['cards'] ?? [];
        $bonus = $context['bonus'] ?? 0;

        $cardPoints = is_array($cards)
            ? (int) array_sum(array_map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0, $cards))
            : 0;

        $bonusPoints = is_numeric($bonus) ? (int) $bonus : 0;

        return (new GameScore())
            ->setSession($session)
            ->setValue($cardPoints + $bonusPoints)
            ->setBreakdown($context)
            ->setCalculatedAt(new DateTimeImmutable());
    }

    public function computeStats(Game $game, ?User $user): array
    {
        $sessions = $this->sessionRepository->findCompletedByGameAndUser($game, $user);
        $wins = 0;
        foreach ($sessions as $session) {
            if (($session->getContext()['is_win'] ?? false) === true) {
                ++$wins;
            }
        }

        return [
            'rounds' => count($sessions),
            'wins' => $wins,
        ];
    }
}
