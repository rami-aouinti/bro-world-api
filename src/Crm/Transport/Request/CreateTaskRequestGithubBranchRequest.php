<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTaskRequestGithubBranchRequest
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Length(max: 255)]
    public ?string $sourceBranch = null;

    public bool $postCommentOnIssue = true;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->name = isset($payload['name']) ? trim((string)$payload['name']) : null;
        $request->sourceBranch = isset($payload['sourceBranch']) ? trim((string)$payload['sourceBranch']) : null;
        $request->postCommentOnIssue = (bool)($payload['postCommentOnIssue'] ?? true);

        return $request;
    }
}
