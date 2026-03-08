<?php

declare(strict_types=1);

namespace App\Recruit\Application\Resource;

use App\General\Application\Rest\RestResource;
use App\Recruit\Domain\Repository\Interfaces\ApplicationRepositoryInterface as Repository;

class ApplicationResource extends RestResource
{
    public function __construct(Repository $repository)
    {
        parent::__construct($repository);
    }
}
