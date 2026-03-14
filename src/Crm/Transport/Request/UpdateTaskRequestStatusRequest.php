<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use App\Crm\Domain\Enum\TaskRequestStatus;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateTaskRequestStatusRequest
{
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    public ?string $status = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->status = isset($payload['status']) ? (string) $payload['status'] : null;

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
