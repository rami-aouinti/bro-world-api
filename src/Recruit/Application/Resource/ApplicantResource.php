<?php

declare(strict_types=1);

namespace App\Recruit\Application\Resource;

use App\General\Application\Rest\RestResource;
use App\Recruit\Domain\Repository\Interfaces\ApplicantRepositoryInterface as Repository;

class ApplicantResource extends RestResource
{
    public function __construct(Repository $repository)
    {
        parent::__construct($repository);
    }
}
