<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Transport\Request\CreateGithubIssueRequest;
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
final readonly class CreateProjectGithubIssueController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmRequestHandler $crmRequestHandler,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/issues', methods: [Request::METHOD_POST])]
    #[Route('/v1/crm/general/projects/{project}/github/issues', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Create Project GitHub Issue',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                examples: [
                    'minimalValid' => new OA\Examples(
                        example: 'minimalValid',
                        summary: 'Exemple minimal valide',
                        value: [
                            'repository' => 'acme/crm-platform',
                            'title' => 'Corriger la synchronisation CRM',
                        ],
                    ),
                    'fullBusiness' => new OA\Examples(
                        example: 'fullBusiness',
                        summary: 'Exemple métier complet',
                        value: [
                            'repository' => 'acme/crm-platform',
                            'title' => 'Automatiser la création des tickets support premium',
                            'body' => 'Contexte: projet CRM enterprise\\nPriorité: P1\\nSource: workflow task request.',
                        ],
                    ),
                ],
                required: ['repository', 'title'],
                properties: [
                    new OA\Property(property: 'repository', type: 'string', example: 'rami-aouinti/bro-world-api'),
                    new OA\Property(property: 'title', type: 'string', example: 'Créer endpoint de synchronisation webhook'),
                    new OA\Property(property: 'body', type: 'string', example: 'Description détaillée générée depuis CRM.', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_CREATED,
                description: 'Issue created.',
                content: new OA\JsonContent(
                    example: [
                        'projectId' => 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12',
                        'repository' => 'acme/crm-platform',
                        'issue' => [
                            'number' => 184,
                            'title' => 'Automatiser la création des tickets support premium',
                            'url' => 'https://github.com/acme/crm-platform/issues/184',
                            'state' => 'open',
                        ],
                    ],
                ),
            ),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Invalid payload.'),
            new OA\Response(
                response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                description: 'GitHub API error.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Validation failed.',
                        'errors' => [
                            [
                                'propertyPath' => 'repository',
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

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateGithubIssueRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse(
            $this->crmGithubService->createIssue($project, (string)$input->repository, (string)$input->title, $input->body),
            JsonResponse::HTTP_CREATED,
        ), $this->errorResponseFactory);
    }
}
