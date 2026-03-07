<?php

declare(strict_types=1);

namespace App\Recruit\Application\Resource;

use App\General\Application\Rest\RestResource;
use App\Recruit\Domain\Repository\Interfaces\BadgeRepositoryInterface as Repository;

class BadgeResource extends RestResource
{
    public function __construct(Repository $repository)
    {
        parent::__construct($repository);
    }
}
