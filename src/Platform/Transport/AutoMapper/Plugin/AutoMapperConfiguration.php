<?php

declare(strict_types=1);

namespace App\Platform\Transport\AutoMapper\Plugin;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Platform\Application\DTO\Plugin\PluginCreate;
use App\Platform\Application\DTO\Plugin\PluginPatch;
use App\Platform\Application\DTO\Plugin\PluginUpdate;

/**
 * @package App\Platform
 */
class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    /**
     * Classes to use specified request mapper.
     *
     * @var array<int, class-string>
     */
    protected static array $requestMapperClasses = [
        PluginCreate::class,
        PluginUpdate::class,
        PluginPatch::class,
    ];

    public function __construct(
        RequestMapper $requestMapper,
    ) {
        parent::__construct($requestMapper);
    }
}
