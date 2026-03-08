<?php

declare(strict_types=1);

namespace App\General\Transport\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ValidationErrorFactory
{
    public static function badRequest(string $message): HttpException
    {
        return new HttpException(JsonResponse::HTTP_BAD_REQUEST, $message);
    }

    public static function unprocessable(string $message): HttpException
    {
        return new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $message);
    }
}
