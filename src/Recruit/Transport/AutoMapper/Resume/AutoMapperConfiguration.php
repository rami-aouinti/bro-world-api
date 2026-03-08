<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Resume;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Recruit\Application\DTO\Resume\ResumeCreate;
use App\Recruit\Application\DTO\Resume\ResumePatch;
use App\Recruit\Application\DTO\Resume\ResumeUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        ResumeCreate::class,
        ResumeUpdate::class,
        ResumePatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
