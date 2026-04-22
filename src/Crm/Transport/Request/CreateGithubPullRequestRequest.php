<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateGithubPullRequestRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[^\s\/]+\/[^\s\/]+$/', message: 'Repository must be in the "owner/name" format.')]
    public ?string $repository = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Assert\NotBlank]
    public ?string $head = null;

    #[Assert\NotBlank]
    public ?string $base = null;

    public ?string $body = null;

    public ?bool $draft = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->repository = isset($payload['repository']) ? trim((string)$payload['repository']) : null;
        $request->title = isset($payload['title']) ? trim((string)$payload['title']) : null;
        $request->head = isset($payload['head']) ? trim((string)$payload['head']) : null;
        $request->base = isset($payload['base']) ? trim((string)$payload['base']) : null;
        $request->body = isset($payload['body']) ? (string)$payload['body'] : null;
        $request->draft = isset($payload['draft']) ? (bool)$payload['draft'] : null;

        return $request;
    }
}
