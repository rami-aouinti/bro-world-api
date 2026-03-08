<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Resume;

use App\General\Transport\AutoMapper\RestRequestMapper;

class RequestMapper extends RestRequestMapper
{
    protected static array $properties = [
        'owner',
        'experiences',
        'educations',
        'skills',
        'languages',
        'certifications',
        'projects',
        'references',
        'hobbies',
    ];
}
