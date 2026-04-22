<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class PatchGithubIssueRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[^\s\/]+\/[^\s\/]+$/', message: 'Repository must be in the "owner/name" format.')]
    public ?string $repository = null;

    #[Assert\Length(max: 255)]
    public ?string $title = null;

    public ?string $body = null;

    #[Assert\Choice(choices: ['open', 'closed'])]
    public ?string $state = null;

    #[Assert\Choice(choices: ['completed', 'reopened', 'not_planned'])]
    public ?string $stateReason = null;

    /** @var list<string>|null */
    public ?array $labels = null;

    /** @var list<string>|null */
    public ?array $assignees = null;

    public ?int $milestone = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->repository = isset($payload['repository']) ? trim((string)$payload['repository']) : null;
        $request->title = isset($payload['title']) ? trim((string)$payload['title']) : null;
        $request->body = isset($payload['body']) ? (string)$payload['body'] : null;
        $request->state = isset($payload['state']) ? trim((string)$payload['state']) : null;
        $request->stateReason = isset($payload['stateReason']) ? trim((string)$payload['stateReason']) : null;
        $request->labels = isset($payload['labels']) && is_array($payload['labels']) ? array_values(array_map(static fn (mixed $value): string => (string)$value, $payload['labels'])) : null;
        $request->assignees = isset($payload['assignees']) && is_array($payload['assignees']) ? array_values(array_map(static fn (mixed $value): string => (string)$value, $payload['assignees'])) : null;
        $request->milestone = isset($payload['milestone']) ? (int)$payload['milestone'] : null;

        return $request;
    }
}
