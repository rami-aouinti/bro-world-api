<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class CrmDateParser
{
    public function __construct(
        private CrmApiErrorResponseFactory $errorResponseFactory
    ) {
    }

    public function parseNullableIso8601(?string $value, string $field): DateTimeImmutable|JsonResponse|null
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return $this->errorResponseFactory->invalidDate($field);
        }
    }
}
