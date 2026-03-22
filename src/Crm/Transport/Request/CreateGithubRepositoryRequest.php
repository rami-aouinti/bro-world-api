<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateGithubRepositoryRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\Length(max: 500)]
    public ?string $description = null;

    public bool $private = true;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->name = isset($payload['name']) ? trim((string)$payload['name']) : null;
        $request->description = isset($payload['description']) ? trim((string)$payload['description']) : null;
        $request->private = (bool)($payload['private'] ?? true);

        return $request;
    }
}
