<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Command;

use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateTaskCommandDto
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

    public ?string $dueAt = null;

    #[Assert\Type(type: 'numeric')]
    public null|int|float|string $estimatedHours = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $projectId = null;

    #[Assert\Uuid]
    public ?string $sprintId = null;

    #[Assert\Type(type: 'array')]
    #[Assert\All([new Assert\Uuid()])]
    public ?array $assigneeIds = null;

    public static function fromPostArray(array $payload): self
    {
        $dto = new self();
        $dto->title = isset($payload['title']) ? (string)$payload['title'] : null;
        $dto->description = isset($payload['description']) ? (string)$payload['description'] : null;
        $dto->status = isset($payload['status']) ? (string)$payload['status'] : null;
        $dto->priority = isset($payload['priority']) ? (string)$payload['priority'] : null;
        $dto->dueAt = isset($payload['dueAt']) ? (string)$payload['dueAt'] : null;
        $dto->estimatedHours = $payload['estimatedHours'] ?? null;
        $dto->projectId = isset($payload['projectId']) ? (string)$payload['projectId'] : null;
        $dto->sprintId = isset($payload['sprintId']) ? (string)$payload['sprintId'] : null;
        $dto->assigneeIds = isset($payload['assigneeIds']) && is_array($payload['assigneeIds']) ? $payload['assigneeIds'] : null;

        return $dto;
    }

    public static function statusChoices(): array
    {
        return array_map(static fn (TaskStatus $status): string => $status->value, TaskStatus::cases());
    }

    public static function priorityChoices(): array
    {
        return array_map(static fn (TaskPriority $priority): string => $priority->value, TaskPriority::cases());
    }
}
