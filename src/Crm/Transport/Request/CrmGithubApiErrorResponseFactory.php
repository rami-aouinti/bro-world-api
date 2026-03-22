<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use App\Crm\Application\Exception\CrmGithubApiException;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CrmGithubApiErrorResponseFactory
{
    public function fromException(CrmGithubApiException $exception): JsonResponse
    {
        return new JsonResponse([
            'message' => $exception->getMessage(),
            'errors' => $exception->getErrors(),
        ], $exception->getStatusCode());
    }
}
