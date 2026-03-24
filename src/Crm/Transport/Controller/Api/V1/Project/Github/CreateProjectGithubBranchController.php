<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Transport\Request\CreateGithubBranchRequest;
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
final readonly class CreateProjectGithubBranchController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmRequestHandler $crmRequestHandler,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/branches/create', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Create Project GitHub Branch',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                examples: [
                    'minimalValid' => new OA\Examples(
                        example: 'minimalValid',
                        summary: 'Exemple minimal valide',
                        value: [
                            'repository' => 'acme/crm-platform',
                            'name' => 'feature/crm-validation-docs',
                        ],
                    ),
                    'fullBusiness' => new OA\Examples(
                        example: 'fullBusiness',
                        summary: 'Exemple métier complet',
                        value: [
                            'repository' => 'acme/crm-platform',
                            'name' => 'feature/enterprise-support-workflow',
                            'sourceBranch' => 'main',
                        ],
                    ),
                ],
                required: ['repository', 'name'],
                properties: [
                    new OA\Property(property: 'repository', type: 'string', example: 'rami-aouinti/bro-world-api'),
                    new OA\Property(property: 'name', type: 'string', example: 'feature/crm-branch-endpoint'),
                    new OA\Property(property: 'sourceBranch', type: 'string', example: 'main', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_CREATED,
                description: 'Branch created on GitHub.',
                content: new OA\JsonContent(
                    example: [
                        'projectId' => 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12',
                        'repository' => 'acme/crm-platform',
                        'branch' => [
                            'name' => 'feature/enterprise-support-workflow',
                            'sha' => 'b87f4cab595d65f6d97fd1199f39ca6d8f1c2381',
                            'url' => 'https://github.com/acme/crm-platform/tree/feature/enterprise-support-workflow',
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
    public function __invoke(string $applicationSlug, Project $project, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateGithubBranchRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse(
            $this->crmGithubService->createBranch($project, (string)$input->repository, (string)$input->name, $input->sourceBranch),
            JsonResponse::HTTP_CREATED,
        ), $this->errorResponseFactory);
    }
}
