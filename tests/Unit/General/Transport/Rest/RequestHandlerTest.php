<?php

declare(strict_types=1);

namespace App\Tests\Unit\General\Transport\Rest;

use App\General\Transport\Rest\RequestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @package App\Tests\Unit\General\Transport\Rest
 */
class RequestHandlerTest extends TestCase
{
    public function testGetSearchTermsNormalizesOrArrayTerms(): void
    {
        $request = new Request(query: ['search' => '{"or":["foo","","foo"]}']);

        self::assertSame(['or' => ['foo']], RequestHandler::getSearchTerms($request));
    }

    public function testGetSearchTermsNormalizesOrStringTerms(): void
    {
        $request = new Request(query: ['search' => '{"or":"foo bar"}']);

        self::assertSame(['or' => ['foo', 'bar']], RequestHandler::getSearchTerms($request));
    }

    public function testGetSearchTermsThrowsWithInvalidOrType(): void
    {
        $request = new Request(query: ['search' => '{"or":123}']);

        try {
            RequestHandler::getSearchTerms($request);
            self::fail('Expected HttpException to be thrown for invalid search operand type.');
        } catch (HttpException $exception) {
            self::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
            self::assertSame(
                "Given search parameter is not valid, 'or' value must be a string or an array of strings.",
                $exception->getMessage(),
            );
        }
    }
}
