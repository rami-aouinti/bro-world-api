<?php

declare(strict_types=1);

namespace App\Page\Transport\AutoMapper\About;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Page\Application\DTO\About\AboutCreate;
use App\Page\Application\DTO\About\AboutPatch;
use App\Page\Application\DTO\About\AboutUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        AboutCreate::class,
        AboutUpdate::class,
        AboutPatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
