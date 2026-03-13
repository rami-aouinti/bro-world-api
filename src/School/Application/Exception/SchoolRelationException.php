<?php

declare(strict_types=1);

namespace App\School\Application\Exception;

use App\General\Application\Exception\Interfaces\ClientErrorInterface;
use RuntimeException;

final class SchoolRelationException extends RuntimeException implements ClientErrorInterface
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function notFound(string $reference): self
    {
        return new self($reference . ' not found', 404);
    }

    public static function unprocessable(string $message): self
    {
        return new self($message, 422);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

