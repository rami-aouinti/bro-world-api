<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class CrmDateParser
{
    public function __construct(private CrmApiErrorResponseFactory $errorResponseFactory)
    {
    }

    public function parseNullableIso8601(?string $value, string $field): DateTimeImmutable|JsonResponse|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        if ($date === false) {
            return $this->errorResponseFactory->invalidDate($field);
        }

        return $date;
    }
}
