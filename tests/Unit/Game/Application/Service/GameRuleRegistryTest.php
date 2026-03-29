<?php

declare(strict_types=1);

namespace App\Tests\Unit\Game\Application\Service;

use App\Game\Application\Service\GameRuleRegistry;
use App\Game\Application\Strategy\GameRuleStrategyInterface;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameCategory;
use PHPUnit\Framework\TestCase;

final class GameRuleRegistryTest extends TestCase
{
    public function testResolveSelectsMatchingStrategy(): void
    {
        $game = (new Game())->setCategory((new GameCategory())->setKey('board'));

        $nonMatching = $this->createMock(GameRuleStrategyInterface::class);
        $nonMatching->method('supports')->with($game)->willReturn(false);

        $matching = $this->createMock(GameRuleStrategyInterface::class);
        $matching->method('supports')->with($game)->willReturn(true);

        $registry = new GameRuleRegistry([$nonMatching, $matching]);

        self::assertSame($matching, $registry->resolve($game));
    }
}
