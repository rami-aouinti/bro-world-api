<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Transport\Request\AddGithubIssueCommentRequest;
use App\Crm\Transport\Request\CrmGithubApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
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
final readonly class AddProjectGithubIssueCommentController
{
    use HandlesGithubApiExceptions;

    public function __construct(private CrmGithubService $crmGithubService, private CrmRequestHandler $crmRequestHandler, private CrmGithubApiErrorResponseFactory $errorResponseFactory)
    {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/issues/{number}/comments', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crm-sales-hub')]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'number', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 42)]
    #[OA\Post(
        summary: 'Add Project GitHub Issue Comment',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['repository', 'body'],
                properties: [
                    new OA\Property(property: 'repository', type: 'string', example: 'rami-aouinti/bro-world-api'),
                    new OA\Property(property: 'body', type: 'string', example: 'On valide cette issue côté CRM.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: JsonResponse::HTTP_CREATED, description: 'Issue comment created on GitHub.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Invalid payload or GitHub validation error.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'GitHub API error while commenting issue.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Project $project, int $number, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, AddGithubIssueCommentRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse(
            $this->crmGithubService->addIssueComment($project, (string)$input->repository, $number, (string)$input->body),
            JsonResponse::HTTP_CREATED,
        ), $this->errorResponseFactory);
    }
}
