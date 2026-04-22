<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class PatchGithubPullRequestRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[^\s\/]+\/[^\s\/]+$/', message: 'Repository must be in the "owner/name" format.')]
    public ?string $repository = null;

    #[Assert\Length(max: 255)]
    public ?string $title = null;

    public ?string $body = null;

    #[Assert\Choice(choices: ['open', 'closed'])]
    public ?string $state = null;

    public ?string $base = null;

    public ?bool $maintainerCanModify = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->repository = isset($payload['repository']) ? trim((string)$payload['repository']) : null;
        $request->title = isset($payload['title']) ? trim((string)$payload['title']) : null;
        $request->body = isset($payload['body']) ? (string)$payload['body'] : null;
        $request->state = isset($payload['state']) ? trim((string)$payload['state']) : null;
        $request->base = isset($payload['base']) ? trim((string)$payload['base']) : null;
        $request->maintainerCanModify = isset($payload['maintainerCanModify']) ? (bool)$payload['maintainerCanModify'] : null;

        return $request;
    }
}
