<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Transport\Request\CreateGithubRepositoryRequest;
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
final readonly class CreateProjectGithubRepositoryController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmRequestHandler $crmRequestHandler,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/repositories/create', methods: [Request::METHOD_POST])]
    #[Route('/v1/crm/general/projects/{project}/github/repositories/create', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Create Project GitHub Repository',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                examples: [
                    'minimalValid' => new OA\Examples(
                        example: 'minimalValid',
                        summary: 'Exemple minimal valide',
                        value: [
                            'name' => 'crm-sync-service',
                        ],
                    ),
                    'fullBusiness' => new OA\Examples(
                        example: 'fullBusiness',
                        summary: 'Exemple métier complet',
                        value: [
                            'name' => 'crm-enterprise-automation',
                            'description' => 'Provisionnement GitHub pour le projet CRM enterprise.',
                            'private' => true,
                        ],
                    ),
                ],
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'crm-sync-service'),
                    new OA\Property(property: 'description', type: 'string', example: 'Repository provisionné depuis CRM.', nullable: true),
                    new OA\Property(property: 'private', type: 'boolean', example: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_CREATED,
                description: 'Repository created on GitHub.',
                content: new OA\JsonContent(
                    example: [
                        'projectId' => 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12',
                        'repository' => [
                            'fullName' => 'acme/crm-enterprise-automation',
                            'private' => true,
                            'defaultBranch' => 'main',
                            'url' => 'https://github.com/acme/crm-enterprise-automation',
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
                                'propertyPath' => 'name',
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

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateGithubRepositoryRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse(
            $this->crmGithubService->createRepository($project, (string)$input->name, $input->description, $input->private),
            JsonResponse::HTTP_CREATED,
        ), $this->errorResponseFactory);
    }
}
