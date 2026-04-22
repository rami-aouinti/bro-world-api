<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Transport\Request\CrmGithubApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Crm\Transport\Request\UpdateGithubIssueStateRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class UpdateProjectGithubIssueStateController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmRequestHandler $crmRequestHandler,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory
    ) {
    }

    #[Route('/v1/crm/projects/{project}/github/issues/{number}', methods: [Request::METHOD_PATCH])]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'number', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 42)]
    #[OA\Patch(
        summary: 'Update Project GitHub Issue State',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['repository', 'state'],
                properties: [
                    new OA\Property(property: 'repository', type: 'string', example: 'rami-aouinti/bro-world-api'),
                    new OA\Property(property: 'state', type: 'string', enum: ['open', 'closed'], example: 'closed'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Issue state updated.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'GitHub API error.'),
        ],
    )]
    public function __invoke(Project $project, int $number, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, UpdateGithubIssueStateRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse(
            $this->crmGithubService->updateIssueState($project, (string)$input->repository, $number, (string)$input->state),
        ), $this->errorResponseFactory);
    }
}
