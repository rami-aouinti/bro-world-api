<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crm\Transport\Request;

use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmDateParser;
use App\Crm\Transport\Request\CrmRequestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CrmRequestHandlerTest extends TestCase
{
    public function testDecodeJsonReturnsErrorForInvalidPayload(): void
    {
        $handler = $this->buildHandler($this->createMock(ValidatorInterface::class));

        $response = $handler->decodeJson(new Request(content: '{"invalid":'));

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testMapAndValidateThrowsWhenMapperMethodDoesNotExist(): void
    {
        $handler = $this->buildHandler($this->createMock(ValidatorInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $handler->mapAndValidate(['name' => 'x'], DummyDto::class, mapperMethod: 'missing');
    }

    public function testMapAndValidateReturnsValidationErrorResponseWhenViolationsExist(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList([
            new ConstraintViolation('msg', null, [], null, 'name', null),
        ]));

        $handler = $this->buildHandler($validator);
        $result = $handler->mapAndValidate(['name' => ''], DummyDto::class);

        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $result->getStatusCode());
    }

    public function testMapAndValidateReturnsDtoWhenValidationPasses(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $handler = $this->buildHandler($validator);
        $result = $handler->mapAndValidate(['name' => 'ok'], DummyDto::class);

        self::assertInstanceOf(DummyDto::class, $result);
        self::assertSame('ok', $result->name);
    }

    private function buildHandler(ValidatorInterface $validator): CrmRequestHandler
    {
        $errorFactory = new CrmApiErrorResponseFactory();

        return new CrmRequestHandler($validator, $errorFactory, new CrmDateParser($errorFactory));
    }
}

final class DummyDto
{
    public string $name = '';

    public static function fromArray(array $payload): self
    {
        $dto = new self();
        $dto->name = (string) ($payload['name'] ?? '');

        return $dto;
    }
}
