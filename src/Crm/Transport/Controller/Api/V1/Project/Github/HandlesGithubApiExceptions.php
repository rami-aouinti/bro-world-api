<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Exception\CrmGithubApiException;
use App\Crm\Transport\Request\CrmGithubApiErrorResponseFactory;
use Closure;
use Symfony\Component\HttpFoundation\JsonResponse;

trait HandlesGithubApiExceptions
{
    protected function withGithubApiErrors(Closure $operation, CrmGithubApiErrorResponseFactory $errorResponseFactory): JsonResponse
    {
        try {
            return $operation();
        } catch (CrmGithubApiException $exception) {
            return $errorResponseFactory->fromException($exception);
        }
    }
}
