<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\Application\Service;

use App\Chat\Application\Service\ReactionTypeParser;
use App\Chat\Domain\Enum\ChatReactionType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ReactionTypeParserTest extends TestCase
{
    public function testParseReturnsReactionType(): void
    {
        $parser = new ReactionTypeParser();

        self::assertSame(ChatReactionType::LIKE, $parser->parse('like'));
    }

    public function testParseThrowsBadRequestWhenReactionIsNotNonEmptyString(): void
    {
        $parser = new ReactionTypeParser();

        try {
            $parser->parse('');
            self::fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertSame('Field "reaction" must be a non-empty string.', $exception->getMessage());
        }
    }

    public function testParseThrowsBadRequestWhenReactionValueIsInvalid(): void
    {
        $parser = new ReactionTypeParser();

        try {
            $parser->parse('invalid-value');
            self::fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertSame('Invalid reaction "invalid-value". Allowed values: ' . implode(', ', ChatReactionType::VALUES) . '.', $exception->getMessage());
        }
    }
}
