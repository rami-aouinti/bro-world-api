<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Transport\Request\AddProjectGithubRepositoryRequest;
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
final readonly class AddProjectGithubRepositoryController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmRequestHandler $crmRequestHandler,
        private ProjectRepository $projectRepository,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/projects/{project}/github/repositories', methods: [Request::METHOD_POST])]
    #[Route('/v1/crm/projects/{project}/gitlab/repositories', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), example: 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12')]
    #[OA\Post(
        description: 'Ajoute un repository GitHub existant au projet CRM courant à partir du fullName `owner/name`.',
        summary: 'Add Project GitHub Repository',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                examples: [
                    new OA\Examples(
                        example: 'addRepo',
                        summary: 'Exemple minimal valide',
                        value: [
                            'fullName' => 'john-root/bro-world-api',
                        ],
                    ),
                ],
                required: ['fullName'],
                properties: [
                    new OA\Property(
                        property: 'fullName',
                        description: 'Nom complet du repository au format owner/name.',
                        type: 'string',
                        maxLength: 255,
                        example: 'john-root/bro-world-api',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Repository ajouté au projet.',
                content: new OA\JsonContent(
                    example: [
                        'id' => 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12',
                        'repository' => [
                            'fullName' => 'john-root/bro-world-api',
                            'defaultBranch' => 'main',
                        ],
                        'repositories' => [
                            [
                                'fullName' => 'john-root/bro-world-api',
                                'defaultBranch' => 'main',
                            ],
                        ],
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Payload JSON invalide.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Invalid JSON payload.',
                        'errors' => [],
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation échouée (ex: fullName manquant ou mal formaté).',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Validation failed.',
                        'errors' => [
                            [
                                'propertyPath' => 'fullName',
                                'message' => 'Repository must be in the "owner/name" format.',
                                'code' => null,
                            ],
                        ],
                    ],
                ),
            ),
            new OA\Response(
                response: 502,
                description: 'Erreur API GitHub (token absent/invalid, repo inaccessible, etc.).',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'GitHub API request failed: HTTP 404 returned for "https://api.github.com/repos/john-root/unknown".',
                        'errors' => [],
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

        $input = $this->crmRequestHandler->mapAndValidate($payload, AddProjectGithubRepositoryRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->withGithubApiErrors(function () use ($project, $input): JsonResponse {
            $repository = $this->crmGithubService->attachRepository($project, (string)$input->fullName);
            $this->projectRepository->save($project);

            return new JsonResponse([
                'id' => $project->getId(),
                'repository' => $repository,
                'repositories' => $this->crmGithubService->listRepositories($project),
            ], JsonResponse::HTTP_CREATED);
        }, $this->errorResponseFactory);
    }
}
