<?php

declare(strict_types=1);

namespace App\Crm\Transport\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorResponse',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed.'),
        new OA\Property(
            property: 'errors',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'propertyPath', type: 'string', nullable: true, example: 'issueTarget'),
                    new OA\Property(property: 'message', type: 'string', nullable: true, example: 'This value is not valid.'),
                    new OA\Property(property: 'code', type: 'string', nullable: true, example: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
                ],
            ),
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmErrorResponse',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed.'),
        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'object')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'JobAcceptedResponse',
    required: ['jobId', 'status'],
    properties: [
        new OA\Property(property: 'jobId', type: 'string', format: 'uuid', example: '0dfde3e4-7095-4fab-bb54-f954ff4c16bd'),
        new OA\Property(property: 'status', type: 'string', example: 'queued'),
        new OA\Property(
            property: 'summary',
            nullable: true,
            properties: [
                new OA\Property(property: 'mode', type: 'string', example: 'dry-run'),
                new OA\Property(property: 'owner', type: 'string', example: 'acme-org'),
                new OA\Property(property: 'issueTarget', type: 'string', enum: ['task', 'task_request'], example: 'task'),
                new OA\Property(property: 'createPublicProject', type: 'boolean', example: true),
                new OA\Property(property: 'plannedActions', type: 'array', items: new OA\Items(type: 'string')),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmGithubBootstrapSyncRequest',
    required: ['token', 'owner'],
    properties: [
        new OA\Property(property: 'token', type: 'string', example: 'ghp_xxxxxxxxx'),
        new OA\Property(property: 'owner', type: 'string', example: 'acme-org'),
        new OA\Property(property: 'issueTarget', type: 'string', enum: ['task', 'task_request'], default: 'task'),
        new OA\Property(property: 'createPublicProject', type: 'boolean', default: true),
        new OA\Property(property: 'dryRun', type: 'boolean', default: false),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PaginatedResponse',
    required: ['items'],
    properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'pagination', type: 'object', nullable: true),
        new OA\Property(property: 'meta', type: 'object', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmContact',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'firstName', type: 'string'),
        new OA\Property(property: 'lastName', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmEmployee',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'fullName', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmProject',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmTask',
    required: ['title', 'projectId'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['todo', 'in_progress', 'blocked', 'done'], nullable: true),
        new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high', 'critical'], nullable: true),
        new OA\Property(property: 'dueAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'estimatedHours', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'projectId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'sprintId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'assigneeIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmTaskRequest',
    required: ['title', 'taskId', 'repositoryId'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], nullable: true),
        new OA\Property(property: 'resolvedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'taskId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'repositoryId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'assigneeIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmGithubRepository',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'fullName', type: 'string'),
        new OA\Property(property: 'private', type: 'boolean', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmGithubIssue',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'number', type: 'integer'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'state', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmGithubBranch',
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'sha', type: 'string', nullable: true),
        new OA\Property(property: 'url', type: 'string', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CrmGithubSyncJob',
    required: ['id', 'applicationSlug', 'owner', 'status', 'projectsCreated', 'reposAttached', 'issuesImported', 'errorsCount', 'errors'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'applicationSlug', type: 'string', example: 'crm-pipeline-pro'),
        new OA\Property(property: 'owner', type: 'string', example: 'acme-org'),
        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'finishedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'running'),
        new OA\Property(property: 'projectsCreated', type: 'integer', example: 3),
        new OA\Property(property: 'reposAttached', type: 'integer', example: 12),
        new OA\Property(property: 'issuesImported', type: 'integer', example: 54),
        new OA\Property(property: 'errorsCount', type: 'integer', example: 1),
        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'object')),
    ],
    type: 'object'
)]
final class CrmSchemas
{
}
