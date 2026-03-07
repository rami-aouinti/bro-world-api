<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Badge;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Recruit\Application\DTO\Badge\BadgeCreate;
use App\Recruit\Application\DTO\Badge\BadgePatch;
use App\Recruit\Application\DTO\Badge\BadgeUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        BadgeCreate::class,
        BadgeUpdate::class,
        BadgePatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
