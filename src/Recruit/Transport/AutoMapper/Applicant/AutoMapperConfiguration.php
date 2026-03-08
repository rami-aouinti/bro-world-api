<?php

declare(strict_types=1);

namespace App\Recruit\Transport\AutoMapper\Applicant;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Recruit\Application\DTO\Applicant\ApplicantCreate;
use App\Recruit\Application\DTO\Applicant\ApplicantPatch;
use App\Recruit\Application\DTO\Applicant\ApplicantUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        ApplicantCreate::class,
        ApplicantUpdate::class,
        ApplicantPatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
