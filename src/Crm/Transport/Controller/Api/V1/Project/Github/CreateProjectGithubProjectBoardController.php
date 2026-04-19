<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Transport\Request\CreateGithubProjectBoardRequest;
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
final readonly class CreateProjectGithubProjectBoardController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmRequestHandler $crmRequestHandler,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/projects', methods: [Request::METHOD_POST])]
    #[Route('/v1/crm/general/projects/{project}/github/projects', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Create Project GitHub Project Board',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                examples: [
                    'minimalValid' => new OA\Examples(
                        example: 'minimalValid',
                        summary: 'Exemple minimal valide',
                        value: [
                            'owner' => 'O_kgDOBfke3Q',
                            'title' => 'CRM Project Board',
                        ],
                    ),
                    'fullBusiness' => new OA\Examples(
                        example: 'fullBusiness',
                        summary: 'Exemple métier complet',
                        value: [
                            'owner' => 'O_kgDOBfke3Q',
                            'title' => 'CRM Enterprise Delivery Board',
                        ],
                    ),
                ],
                required: ['owner', 'title'],
                properties: [
                    new OA\Property(property: 'owner', description: 'GitHub owner node id', type: 'string', example: 'O_kgDOBfke3Q'),
                    new OA\Property(property: 'title', type: 'string', example: 'CRM Project Board'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_CREATED,
                description: 'Project board created.',
                content: new OA\JsonContent(
                    example: [
                        'projectId' => 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12',
                        'project' => [
                            'nodeId' => 'PVT_kwDOBfke3c4A4zqS',
                            'number' => 5,
                            'title' => 'CRM Enterprise Delivery Board',
                            'url' => 'https://github.com/orgs/acme/projects/5',
                        ],
                    ],
                ),
            ),
            new OA\Response(
                response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                description: 'GitHub API error.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Validation failed.',
                        'errors' => [
                            [
                                'propertyPath' => 'owner',
                                'message' => 'This value should not be blank.',
                                'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                            ],
                        ],
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(Project $project, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateGithubProjectBoardRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse(
            $this->crmGithubService->createProjectBoard($project, (string)$input->owner, (string)$input->title),
            JsonResponse::HTTP_CREATED,
        ), $this->errorResponseFactory);
    }
}
