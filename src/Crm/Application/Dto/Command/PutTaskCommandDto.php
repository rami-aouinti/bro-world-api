<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Command;

use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PutTaskCommandDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    public ?string $status = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'priorityChoices'])]
    public ?string $priority = null;

    #[Assert\DateTime]
    public ?string $dueAt = null;

    #[Assert\Type(type: 'numeric')]
    public null|int|float|string $estimatedHours = null;

    #[Assert\Uuid]
    public ?string $sprintId = null;

    public bool $hasTitle = false;
    public bool $hasDescription = false;
    public bool $hasStatus = false;
    public bool $hasPriority = false;
    public bool $hasDueAt = false;
    public bool $hasEstimatedHours = false;
    public bool $hasSprintId = false;

    public static function fromArray(array $payload): self
    {
        $dto = new self();

        foreach (['title', 'description', 'status', 'priority', 'dueAt', 'estimatedHours', 'sprintId'] as $field) {
            $flag = 'has' . ucfirst($field);
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $dto->{$flag} = true;
            if ($field === 'estimatedHours') {
                $dto->estimatedHours = $payload[$field];

                continue;
            }

            $dto->{$field} = $payload[$field] !== null ? (string)$payload[$field] : null;
        }

        return $dto;
    }

    #[Assert\Callback]
    public function validatePutReplacement(ExecutionContextInterface $context): void
    {
        foreach (['title', 'description', 'status', 'priority', 'dueAt', 'estimatedHours', 'sprintId'] as $field) {
            $flag = 'has' . ucfirst($field);
            if ($this->{$flag}) {
                continue;
            }

            $context->buildViolation('This field is required for PUT replacement.')
                ->atPath($field)
                ->addViolation();
        }
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
