<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use App\Crm\Domain\Enum\ProjectStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateProjectRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Length(max: 64)]
    public ?string $code = null;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    public ?string $status = null;

    #[Assert\DateTime]
    public ?string $startedAt = null;

    #[Assert\DateTime]
    public ?string $dueAt = null;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Uuid]
    public ?string $companyId = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->name = isset($payload['name']) ? (string)$payload['name'] : null;
        $request->code = isset($payload['code']) ? (string)$payload['code'] : null;
        $request->description = isset($payload['description']) ? (string)$payload['description'] : null;
        $request->status = isset($payload['status']) ? (string)$payload['status'] : null;
        $request->startedAt = isset($payload['startedAt']) ? (string)$payload['startedAt'] : null;
        $request->dueAt = isset($payload['dueAt']) ? (string)$payload['dueAt'] : null;
        $request->companyId = isset($payload['companyId']) ? (string)$payload['companyId'] : null;

        return $request;
    }

    /**
     * @return list<string>
     */
    public static function statusChoices(): array
    {
        return array_map(static fn (ProjectStatus $status): string => $status->value, ProjectStatus::cases());
    }
}
