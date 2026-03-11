<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use Ramsey\Uuid\Uuid;

class OperationIdGeneratorService
{
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}
