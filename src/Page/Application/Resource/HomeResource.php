<?php

declare(strict_types=1);

namespace App\Page\Application\Resource;

use App\General\Application\Rest\RestResource;
use App\Page\Domain\Repository\Interfaces\HomeRepositoryInterface as Repository;

class HomeResource extends RestResource
{
    public function __construct(Repository $repository)
    {
        parent::__construct($repository);
    }
}
