<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\ProjectReadService;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class ListProjectsController
{
    public function __construct(
        private ProjectReadService $projectReadService,
    ) {
    }

    #[Route('/v1/crm/projects', methods: [Request::METHOD_GET])]
    #[OA\Parameter(ref: '#/components/parameters/page')]
    #[OA\Parameter(ref: '#/components/parameters/limit')]
    #[OA\Parameter(ref: '#/components/parameters/q')]
    #[OA\Get(
        description: 'Exécute l action metier List Projects dans le perimetre de l application CRM.',
        summary: 'List Projects',
        security: []
    )]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->projectReadService->getListGlobal($request));
    }
}
