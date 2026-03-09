<?php

declare(strict_types=1);

namespace App\Page\Transport\AutoMapper\Home;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Page\Application\DTO\Home\HomeCreate;
use App\Page\Application\DTO\Home\HomePatch;
use App\Page\Application\DTO\Home\HomeUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        HomeCreate::class,
        HomeUpdate::class,
        HomePatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
