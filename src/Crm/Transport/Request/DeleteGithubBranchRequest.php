<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class DeleteGithubBranchRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[^\s\/]+\/[^\s\/]+$/', message: 'Repository must be in the "owner/name" format.')]
    public ?string $repository = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->repository = isset($payload['repository']) ? trim((string)$payload['repository']) : null;
        $request->name = isset($payload['name']) ? trim((string)$payload['name']) : null;

        return $request;
    }
}
