<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\ProjectReadService;
use App\Crm\Domain\Entity\Project;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class GetGeneralProjectController
{
    public function __construct(private ProjectReadService $projectReadService)
    {
    }

    #[Route('/v1/crm/general/projects/{project}', methods: [Request::METHOD_GET])]
    public function __invoke(Project $project): JsonResponse
    {
        $payload = $this->projectReadService->getDetailGlobal($project);
        if ($payload === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Project not found.');
        }

        return new JsonResponse($payload);
    }
}
