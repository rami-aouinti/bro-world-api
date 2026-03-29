<?php

declare(strict_types=1);

namespace App\Tests\Unit\Game\Application\Strategy;

use App\Game\Application\Strategy\BoardGameRuleStrategy;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameSession;
use App\Game\Infrastructure\Repository\GameSessionRepository;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

final class BoardGameRuleStrategyTest extends TestCase
{
    public function testComputeStatsAggregatesMatchesAndUniquePlayers(): void
    {
        $game = new Game();
        $userA = $this->createConfiguredMock(User::class, ['getId' => 'u1']);
        $userB = $this->createConfiguredMock(User::class, ['getId' => 'u2']);

        $session1 = (new GameSession())->setUser($userA);
        $session2 = (new GameSession())->setUser($userA);
        $session3 = (new GameSession())->setUser($userB);

        $sessionRepository = $this->createMock(GameSessionRepository::class);
        $sessionRepository
            ->method('findCompletedByGameAndUser')
            ->with($game, null)
            ->willReturn([$session1, $session2, $session3]);

        $strategy = new BoardGameRuleStrategy($sessionRepository);

        $stats = $strategy->computeStats($game, null);

        self::assertSame(3, $stats['matches']);
        self::assertSame(2, $stats['unique_players']);
    }
}
