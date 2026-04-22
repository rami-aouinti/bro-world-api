<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Application;

use App\Recruit\Application\Service\PipelineBoardService;
use App\Recruit\Application\Service\RecruitResolverService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class PrivatePipelineController
{
    public function __construct(
        private RecruitResolverService $recruitResolverService,
        private PipelineBoardService $pipelineBoardService,
    ) {
    }

    #[Route(path: '/v1/recruit/private/pipeline', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Kanban pipeline privé des candidatures (colonnes + candidats + métriques).',
        parameters: [
            new OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'jobId', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'owner', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', description: 'YYYY-MM-DD')),
            new OA\Parameter(name: 'source', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['resume', 'manual'])),
            new OA\Parameter(name: 'tags', in: 'query', required: false, schema: new OA\Schema(type: 'string', description: 'Label du tag job.')),
        ],
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $recruit = $this->recruitResolverService->resolveFromRequest($request);

        if ($recruit->getApplication()?->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access pipeline for this application.');
        }

        return new JsonResponse($this->pipelineBoardService->getPipeline($recruit, $request));
    }
}
