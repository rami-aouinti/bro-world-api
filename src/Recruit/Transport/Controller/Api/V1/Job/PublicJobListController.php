<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\Recruit\Application\Service\JobPublicListService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Recruit Job')]
class PublicJobListController
{
    public function __construct(private readonly JobPublicListService $jobPublicListService)
    {
    }

    #[Route(path: '/v1/recruit/public/{applicationSlug}/jobs', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Liste publique des offres jobs, paginée et filtrable.',
        security: [],
        parameters: [
            new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'company', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'salaryMin', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'salaryMax', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'contractType', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['CDI', 'CDD', 'Freelance', 'Internship'])),
            new OA\Parameter(name: 'workMode', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Onsite', 'Remote', 'Hybrid'])),
            new OA\Parameter(name: 'schedule', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Vollzeit', 'Teilzeit', 'Contract'])),
            new OA\Parameter(name: 'postedAtLabel', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '7d')),
            new OA\Parameter(name: 'location', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
    )]
    public function __invoke(Request $request, string $applicationSlug): JsonResponse
    {
        return new JsonResponse($this->jobPublicListService->getList($request, $applicationSlug));
    }
}
