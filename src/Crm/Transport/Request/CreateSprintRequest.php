<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use App\Crm\Domain\Enum\SprintStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateSprintRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Length(max: 5000)]
    public ?string $goal = null;

    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    public ?string $status = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Uuid]
    public ?string $projectId = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->name = isset($payload['name']) ? (string)$payload['name'] : null;
        $request->goal = isset($payload['goal']) ? (string)$payload['goal'] : null;
        $request->status = isset($payload['status']) ? (string)$payload['status'] : null;
        $request->startDate = isset($payload['startDate']) ? (string)$payload['startDate'] : null;
        $request->endDate = isset($payload['endDate']) ? (string)$payload['endDate'] : null;
        $request->projectId = isset($payload['projectId']) ? (string)$payload['projectId'] : null;

        return $request;
    }

    /**
     * @return list<string>
     */
    public static function statusChoices(): array
    {
        return array_map(static fn (SprintStatus $status): string => $status->value, SprintStatus::cases());
    }
}
