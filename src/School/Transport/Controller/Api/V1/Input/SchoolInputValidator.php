<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Input;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function array_map;
use function iterator_to_array;

final readonly class SchoolInputValidator
{
    public function __construct(
        private ValidatorInterface $validator
    ) {
    }

    public function validate(object $input): ?JsonResponse
    {
        $violations = $this->validator->validate($input);
        if ($violations->count() === 0) {
            return null;
        }

        return new JsonResponse([
            'message' => 'Validation failed.',
            'code' => 'SCHOOL_VALIDATION_FAILED',
            'details' => array_map(
                static fn (ConstraintViolationInterface $violation): array => [
                    'propertyPath' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                    'code' => $violation->getCode(),
                ],
                iterator_to_array($violations),
            ),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
