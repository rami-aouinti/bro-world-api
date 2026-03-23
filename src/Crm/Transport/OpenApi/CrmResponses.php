<?php

declare(strict_types=1);

namespace App\Crm\Transport\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Response(response: 'ValidationFailed422', description: 'Validation failed.', content: new OA\JsonContent(ref: '#/components/schemas/CrmErrorResponse'))]
#[OA\Response(response: 'NotFound404', description: 'Resource not found.', content: new OA\JsonContent(ref: '#/components/schemas/CrmErrorResponse'))]
#[OA\Response(response: 'Unauthorized401', description: 'Authentication required.', content: new OA\JsonContent(ref: '#/components/schemas/CrmErrorResponse'))]
#[OA\Response(response: 'Forbidden403', description: 'Access denied.', content: new OA\JsonContent(ref: '#/components/schemas/CrmErrorResponse'))]
#[OA\Response(
    response: 'JobAccepted202',
    description: 'Job queued.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/JobAcceptedResponse',
        examples: [
            'queued' => new OA\Examples(
                example: 'queued',
                summary: 'Job queued',
                value: [
                    'jobId' => '0dfde3e4-7095-4fab-bb54-f954ff4c16bd',
                    'status' => 'queued',
                ],
            ),
            'queuedDryRun' => new OA\Examples(
                example: 'queuedDryRun',
                summary: 'Dry-run queued',
                value: [
                    'jobId' => '0dfde3e4-7095-4fab-bb54-f954ff4c16bd',
                    'status' => 'queued',
                    'summary' => [
                        'mode' => 'dry-run',
                        'owner' => 'acme-org',
                        'issueTarget' => 'task',
                        'createPublicProject' => true,
                        'plannedActions' => [
                            'Scan repositories and issues from the configured owner.',
                            'Map or create CRM entities from GitHub metadata.',
                            'No persistence changes will be committed in dry-run mode.',
                        ],
                    ],
                ],
            ),
        ],
    ),
)]
#[OA\Response(
    response: 'BadRequest400',
    description: 'Invalid payload.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
        examples: [
            'invalidJson' => new OA\Examples(
                example: 'invalidJson',
                summary: 'Malformed JSON payload',
                value: [
                    'message' => 'Invalid JSON payload.',
                    'errors' => [],
                ],
            ),
        ],
    ),
)]
#[OA\Response(
    response: 'BusinessRule422',
    description: 'Business consistency/import error.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/ErrorResponse',
        examples: [
            'ownerOutOfScope' => new OA\Examples(
                example: 'ownerOutOfScope',
                summary: 'Owner inaccessible for current CRM scope',
                value: [
                    'message' => 'Owner is outside current CRM scope.',
                    'errors' => [
                        [
                            'propertyPath' => 'owner',
                            'message' => 'Owner is outside current CRM scope.',
                            'code' => 'reference.out_of_scope',
                        ],
                    ],
                ],
            ),
        ],
    ),
)]
final class CrmResponses
{
}
