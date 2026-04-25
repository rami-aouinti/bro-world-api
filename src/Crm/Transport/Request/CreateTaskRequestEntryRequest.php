<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use App\Crm\Domain\Enum\TaskRequestStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateTaskRequestEntryRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    public ?string $status = null;

    #[Assert\DateTime]
    public ?string $resolvedAt = null;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Uuid]
    public ?string $taskId = null;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Uuid]
    public ?string $repositoryId = null;

    #[Assert\Type(type: 'array')]
    #[Assert\All([
        new Assert\Uuid(),
    ])]
    public ?array $assigneeIds = null;

    #[Assert\Type(type: 'numeric')]
    #[Assert\GreaterThanOrEqual(0)]
    public null|int|float|string $plannedHours = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->title = isset($payload['title']) ? (string)$payload['title'] : null;
        $request->description = isset($payload['description']) ? (string)$payload['description'] : null;
        $request->status = isset($payload['status']) ? (string)$payload['status'] : null;
        $request->resolvedAt = isset($payload['resolvedAt']) ? (string)$payload['resolvedAt'] : null;
        $request->taskId = isset($payload['taskId']) ? (string)$payload['taskId'] : null;
        $request->repositoryId = isset($payload['repositoryId']) ? (string)$payload['repositoryId'] : null;
        $request->assigneeIds = isset($payload['assigneeIds']) && is_array($payload['assigneeIds']) ? $payload['assigneeIds'] : $payload['assigneeIds'] ?? null;
        $request->plannedHours = $payload['plannedHours'] ?? null;

        return $request;
    }

    /**
     * @return list<string>
     */
    public static function statusChoices(): array
    {
        return array_map(static fn (TaskRequestStatus $status): string => $status->value, TaskRequestStatus::cases());
    }
}
