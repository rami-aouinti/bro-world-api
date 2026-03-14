<?php

declare(strict_types=1);

namespace App\School\Transport\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SchoolError',
    required: ['message', 'code', 'details'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Forbidden application scope access.'),
        new OA\Property(property: 'code', type: 'string', example: 'SCHOOL_FORBIDDEN'),
        new OA\Property(property: 'details', type: 'array', items: new OA\Items(type: 'object')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'SchoolValidationError',
    required: ['message', 'code', 'details'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed.'),
        new OA\Property(property: 'code', type: 'string', example: 'SCHOOL_VALIDATION_FAILED'),
        new OA\Property(
            property: 'details',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'propertyPath', type: 'string', example: 'classId'),
                    new OA\Property(property: 'message', type: 'string', example: 'This value should not be blank.'),
                    new OA\Property(property: 'code', type: 'string', nullable: true),
                ],
            ),
        ),
    ],
    type: 'object'
)]
final class SchoolErrorSchemas
{
}
