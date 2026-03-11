<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Job;

use App\General\Transport\AutoMapper\RestRequestMapper;

class RequestMapper extends RestRequestMapper
{
    protected static array $properties = ['recruit', 'title', 'location', 'contractType', 'workMode', 'schedule', 'experienceLevel', 'yearsExperienceMin', 'yearsExperienceMax', 'isPublished', 'summary', 'missionTitle', 'missionDescription', 'responsibilities', 'profile', 'benefits'];
}
