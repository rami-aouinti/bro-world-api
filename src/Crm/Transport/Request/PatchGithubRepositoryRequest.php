<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class PatchGithubRepositoryRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[^\s\/]+\/[^\s\/]+$/', message: 'Repository must be in the "owner/name" format.')]
    public ?string $repository = null;

    #[Assert\Length(max: 255)]
    public ?string $name = null;

    public ?string $description = null;

    public ?bool $private = null;

    public ?string $defaultBranch = null;

    public ?bool $archived = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->repository = isset($payload['repository']) ? trim((string)$payload['repository']) : null;
        $request->name = isset($payload['name']) ? trim((string)$payload['name']) : null;
        $request->description = isset($payload['description']) ? (string)$payload['description'] : null;
        $request->private = isset($payload['private']) ? (bool)$payload['private'] : null;
        $request->defaultBranch = isset($payload['defaultBranch']) ? trim((string)$payload['defaultBranch']) : null;
        $request->archived = isset($payload['archived']) ? (bool)$payload['archived'] : null;

        return $request;
    }
}
