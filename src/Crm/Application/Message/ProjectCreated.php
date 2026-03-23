<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

use App\General\Domain\Message\Interfaces\MessageLowInterface;

final readonly class ProjectCreated implements MessageLowInterface
{
    public function __construct(
        public string $projectId,
        public string $applicationSlug,
    ) {
    }
}
