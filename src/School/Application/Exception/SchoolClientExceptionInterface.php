<?php

declare(strict_types=1);

namespace App\School\Application\Exception;

use App\General\Application\Exception\Interfaces\ClientErrorInterface;

interface SchoolClientExceptionInterface extends ClientErrorInterface
{
    public function getErrorCode(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDetails(): array;
}

