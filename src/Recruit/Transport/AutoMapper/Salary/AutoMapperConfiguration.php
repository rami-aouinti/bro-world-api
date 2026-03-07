<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Salary;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Recruit\Application\DTO\Salary\SalaryCreate;
use App\Recruit\Application\DTO\Salary\SalaryPatch;
use App\Recruit\Application\DTO\Salary\SalaryUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        SalaryCreate::class,
        SalaryUpdate::class,
        SalaryPatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
