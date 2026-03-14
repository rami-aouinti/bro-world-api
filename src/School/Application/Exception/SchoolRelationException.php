<?php

declare(strict_types=1);

namespace App\School\Application\Exception;

use App\General\Application\Exception\Interfaces\ClientErrorInterface;
use RuntimeException;

final class SchoolRelationException extends RuntimeException implements ClientErrorInterface, SchoolClientExceptionInterface
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $errorCode,
        private readonly array $details = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function notFound(string $reference): self
    {
        return new self($reference . ' not found', 404, 'SCHOOL_RELATION_NOT_FOUND');
    }

    public static function unprocessable(string $message): self
    {
        return new self($message, 422, 'SCHOOL_RELATION_UNPROCESSABLE');
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
