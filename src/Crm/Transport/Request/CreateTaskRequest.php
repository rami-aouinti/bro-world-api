<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateTaskRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    public ?string $status = null;

    #[Assert\Choice(callback: [self::class, 'priorityChoices'])]
    public ?string $priority = null;

    #[Assert\DateTime]
    public ?string $dueAt = null;

    #[Assert\Type(type: 'numeric')]
    public null|int|float|string $estimatedHours = null;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Uuid]
    public ?string $projectId = null;

    #[Assert\Uuid]
    public ?string $sprintId = null;

    #[Assert\Type(type: 'array')]
    #[Assert\All([
        new Assert\Uuid(),
    ])]
    public ?array $assigneeIds = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->title = isset($payload['title']) ? (string)$payload['title'] : null;
        $request->description = isset($payload['description']) ? (string)$payload['description'] : null;
        $request->status = isset($payload['status']) ? (string)$payload['status'] : null;
        $request->priority = isset($payload['priority']) ? (string)$payload['priority'] : null;
        $request->dueAt = isset($payload['dueAt']) ? (string)$payload['dueAt'] : null;
        $request->estimatedHours = $payload['estimatedHours'] ?? null;
        $request->projectId = isset($payload['projectId']) ? (string)$payload['projectId'] : null;
        $request->sprintId = isset($payload['sprintId']) ? (string)$payload['sprintId'] : null;
        $request->assigneeIds = isset($payload['assigneeIds']) && is_array($payload['assigneeIds']) ? $payload['assigneeIds'] : $payload['assigneeIds'] ?? null;

        return $request;
    }

    /**
     * @return list<string>
     */
    public static function statusChoices(): array
    {
        return array_map(static fn (TaskStatus $status): string => $status->value, TaskStatus::cases());
    }

    /**
     * @return list<string>
     */
    public static function priorityChoices(): array
    {
        return array_map(static fn (TaskPriority $priority): string => $priority->value, TaskPriority::cases());
    }
}
