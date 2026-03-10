<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Salary;

use App\General\Transport\AutoMapper\RestRequestMapper;

class RequestMapper extends RestRequestMapper
{
    protected static array $properties = ['min', 'max', 'currency', 'period'];
}
