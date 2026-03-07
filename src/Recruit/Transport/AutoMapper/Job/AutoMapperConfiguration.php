<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Job;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Recruit\Application\DTO\Job\JobCreate;
use App\Recruit\Application\DTO\Job\JobPatch;
use App\Recruit\Application\DTO\Job\JobUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        JobCreate::class,
        JobUpdate::class,
        JobPatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
