<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\CrmRepository;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\CrmProjectRepositoryRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmGithubApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Crm\Transport\Request\DeleteProjectGithubRepositoryRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class DeleteProjectGithubRepositoryController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmProjectRepositoryRepository $crmProjectRepositoryRepository,
        private CrmRequestHandler $crmRequestHandler,
        private CrmApiErrorResponseFactory $apiErrorResponseFactory,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/repositories/{repositoryId}', methods: [Request::METHOD_DELETE])]
    #[Route('/v1/crm/general/projects/{project}/github/repositories/{repositoryId}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crm-sales-hub')]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), example: 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12')]
    #[OA\Parameter(name: 'repositoryId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), example: '03463358-2e8f-4f63-a893-69d5313b05d2')]
    #[OA\Parameter(name: 'deleteRemote', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: false), example: true)]
    #[OA\Delete(
        description: 'Détache un repository GitHub du projet CRM. Optionnellement supprime aussi le repository côté GitHub avec deleteRemote=true.',
        summary: 'Delete Project GitHub Repository',
        responses: [
            new OA\Response(response: 204, description: 'Repository détaché.'),
            new OA\Response(response: 404, description: 'Repository inconnu ou non lié à ce projet.'),
            new OA\Response(response: 422, description: 'Validation échouée ou erreur API GitHub.'),
        ],
    )]
    public function __invoke(Project $project, string $repositoryId, Request $request): JsonResponse
    {
        $input = $this->crmRequestHandler->mapAndValidate($request->query->all(), DeleteProjectGithubRepositoryRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->withGithubApiErrors(function () use ($project, $repositoryId, $input): JsonResponse {
            $repository = $this->crmProjectRepositoryRepository->find($repositoryId);
            if (!$repository instanceof CrmRepository || $repository->getProject()?->getId() !== $project->getId()) {
                return $this->apiErrorResponseFactory->notFoundReference('repositoryId');
            }

            if ($input->deleteRemote === true) {
                $this->crmGithubService->deleteRepository($project, $repository->getFullName());
            }

            $project->removeRepository($repository);
            $this->crmProjectRepositoryRepository->remove($repository);

            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }, $this->errorResponseFactory);
    }
}
