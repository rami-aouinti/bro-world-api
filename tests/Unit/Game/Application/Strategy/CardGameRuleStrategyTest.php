<?php

declare(strict_types=1);

namespace App\Tests\Unit\Game\Application\Strategy;

use App\Game\Application\Strategy\CardGameRuleStrategy;
use App\Game\Domain\Entity\GameSession;
use App\Game\Infrastructure\Repository\GameSessionRepository;
use PHPUnit\Framework\TestCase;

final class CardGameRuleStrategyTest extends TestCase
{
    public function testComputeScoreUsesCardsAndBonusFromContext(): void
    {
        $strategy = new CardGameRuleStrategy($this->createMock(GameSessionRepository::class));
        $session = (new GameSession())->setContext([
            'cards' => [5, 8, 10],
            'bonus' => 4,
        ]);

        $score = $strategy->computeScore($session);

        self::assertSame(27, $score->getValue());
        self::assertSame($session, $score->getSession());
    }
}
