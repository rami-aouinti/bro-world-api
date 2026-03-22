<?php

declare(strict_types=1);

namespace App\Crm\Application\Exception;

use RuntimeException;

final class CrmGithubApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 502,
        private readonly array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<int|string,mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
