<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\Recruit\Application\Service\JobPublicDetailService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Recruit Job')]
readonly class PublicJobDetailController
{
    public function __construct(
        private JobPublicDetailService $jobPublicDetailService
    ) {
    }

    #[Route(path: '/v1/recruit/public/jobs/{jobSlug}', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Détail public d\'un job avec jobs similaires indexés.',
        security: [],
        parameters: [
            new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'jobSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
    )]
    public function __invoke(string $applicationSlug, string $jobSlug): JsonResponse
    {
        return new JsonResponse($this->jobPublicDetailService->getDetail($applicationSlug, $jobSlug));
    }
}
