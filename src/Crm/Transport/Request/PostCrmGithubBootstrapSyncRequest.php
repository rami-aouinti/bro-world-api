<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class PostCrmGithubBootstrapSyncRequest
{
    public const string ISSUE_TARGET_TASK = 'task';
    public const string ISSUE_TARGET_TASK_REQUEST = 'task_request';

    #[Assert\NotBlank]
    public ?string $token = null;

    #[Assert\NotBlank]
    public ?string $owner = null;

    #[Assert\Choice(choices: [self::ISSUE_TARGET_TASK, self::ISSUE_TARGET_TASK_REQUEST])]
    public string $issueTarget = self::ISSUE_TARGET_TASK;

    public bool $createPublicProject = true;

    public bool $dryRun = false;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->token = isset($payload['token']) ? trim((string)$payload['token']) : null;
        $request->owner = isset($payload['owner']) ? trim((string)$payload['owner']) : null;
        $request->issueTarget = isset($payload['issueTarget']) ? trim((string)$payload['issueTarget']) : self::ISSUE_TARGET_TASK;
        $request->createPublicProject = (bool)($payload['createPublicProject'] ?? true);
        $request->dryRun = (bool)($payload['dryRun'] ?? false);

        return $request;
    }
}
