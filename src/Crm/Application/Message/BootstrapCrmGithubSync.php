<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

use App\General\Domain\Message\Interfaces\MessageLowInterface;

final readonly class BootstrapCrmGithubSync implements MessageLowInterface
{
    public function __construct(
        public string $jobId,
        public string $applicationSlug,
        public string $token,
        public string $owner,
        public string $issueTarget,
        public bool $createPublicProject,
        public bool $dryRun,
    ) {
    }
}
