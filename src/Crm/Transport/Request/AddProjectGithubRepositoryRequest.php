<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class AddProjectGithubRepositoryRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[^\s\/]+\/[^\s\/]+$/', message: 'Repository must be in the "owner/name" format.')]
    public ?string $fullName = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->fullName = isset($payload['fullName']) ? trim((string)$payload['fullName']) : null;

        return $request;
    }
}
