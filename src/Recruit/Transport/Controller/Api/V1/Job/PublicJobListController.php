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

    #[Route(path: '/v1/recruit/job/public', methods: [Request::METHOD_GET])]
    #[OA\Get(security: [], summary: 'Liste publique des offres jobs, paginée et filtrable.')]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->jobPublicListService->getList($request));
    }
}
