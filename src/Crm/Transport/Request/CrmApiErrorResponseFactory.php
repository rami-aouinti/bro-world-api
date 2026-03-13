<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

use function iterator_to_array;

final class CrmApiErrorResponseFactory
{
    public function invalidJson(): JsonResponse
    {
        return $this->response(JsonResponse::HTTP_BAD_REQUEST, 'Invalid JSON payload.');
    }

    public function invalidDate(string $field): JsonResponse
    {
        return $this->response(JsonResponse::HTTP_BAD_REQUEST, sprintf('Invalid date format for "%s".', $field));
    }

    public function notFoundReference(string $field): JsonResponse
    {
        return $this->response(JsonResponse::HTTP_NOT_FOUND, sprintf('Unknown "%s" in this CRM scope.', $field));
    }

    public function outOfScopeReference(string $message): JsonResponse
    {
        return $this->response(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $message);
    }

    public function validationFailed(ConstraintViolationListInterface $violations): JsonResponse
    {
        return $this->response(
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Validation failed.',
            array_map(
                static fn (ConstraintViolationInterface $violation): array => [
                    'propertyPath' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                    'code' => $violation->getCode(),
                ],
                iterator_to_array($violations),
            ),
        );
    }

    private function response(int $statusCode, string $message, array $errors = []): JsonResponse
    {
        return new JsonResponse([
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}
