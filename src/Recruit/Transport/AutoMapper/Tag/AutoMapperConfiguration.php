<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Tag;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Recruit\Application\DTO\Tag\TagCreate;
use App\Recruit\Application\DTO\Tag\TagPatch;
use App\Recruit\Application\DTO\Tag\TagUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        TagCreate::class,
        TagUpdate::class,
        TagPatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
