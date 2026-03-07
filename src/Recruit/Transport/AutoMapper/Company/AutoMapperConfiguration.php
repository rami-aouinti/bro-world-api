<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Company;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Recruit\Application\DTO\Company\CompanyCreate;
use App\Recruit\Application\DTO\Company\CompanyPatch;
use App\Recruit\Application\DTO\Company\CompanyUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        CompanyCreate::class,
        CompanyUpdate::class,
        CompanyPatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
