<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

final class MoveGithubProjectItemRequest
{
    public ?string $afterItemId = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->afterItemId = isset($payload['afterItemId']) ? trim((string)$payload['afterItemId']) : null;

        return $request;
    }
}
