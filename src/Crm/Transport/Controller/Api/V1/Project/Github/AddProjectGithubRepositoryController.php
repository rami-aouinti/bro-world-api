<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Transport\Request\AddProjectGithubRepositoryRequest;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class AddProjectGithubRepositoryController
{
    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmRequestHandler $crmRequestHandler,
        private ProjectRepository $projectRepository,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/repositories', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Project $project, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, AddProjectGithubRepositoryRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $repository = $this->crmGithubService->attachRepository($project, (string)$input->fullName);
            $this->projectRepository->save($project);

            return new JsonResponse([
                'id' => $project->getId(),
                'repository' => $repository,
                'repositories' => $this->crmGithubService->listRepositories($project),
            ], JsonResponse::HTTP_CREATED);
        } catch (RuntimeException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage(),
                'errors' => [],
            ], JsonResponse::HTTP_BAD_GATEWAY);
        }
    }
}
