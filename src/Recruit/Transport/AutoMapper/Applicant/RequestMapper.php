<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Applicant;

use App\General\Transport\AutoMapper\RestRequestMapper;

class RequestMapper extends RestRequestMapper
{
    protected static array $properties = ['user', 'resume', 'coverLetter'];
}
