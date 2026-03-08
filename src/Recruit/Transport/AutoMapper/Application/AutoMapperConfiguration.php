<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Application;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Recruit\Application\DTO\Application\ApplicationCreate;
use App\Recruit\Application\DTO\Application\ApplicationPatch;
use App\Recruit\Application\DTO\Application\ApplicationUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        ApplicationCreate::class,
        ApplicationUpdate::class,
        ApplicationPatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
