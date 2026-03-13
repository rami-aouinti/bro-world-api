<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Support;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationInterface;

use function array_map;
use function iterator_to_array;

final class ValidationResponseFactory
{
    public static function invalidJson(string $message = 'Invalid JSON payload.'): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Validation failed.',
            'errors' => [[
                'field' => 'payload',
                'message' => $message,
                'code' => 'INVALID_JSON',
            ]],
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    /**
     * @param iterable<ConstraintViolationInterface> $violations
     */
    public static function fromViolations(iterable $violations): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Validation failed.',
            'errors' => array_map(
                static fn (ConstraintViolationInterface $violation): array => [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                    'code' => $violation->getCode(),
                ],
                iterator_to_array($violations),
            ),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
