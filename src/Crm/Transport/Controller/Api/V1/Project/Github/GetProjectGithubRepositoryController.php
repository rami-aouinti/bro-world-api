<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Transport\Request\CrmGithubApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class GetProjectGithubRepositoryController
{
    use HandlesGithubApiExceptions;

    public function __construct(private CrmGithubService $crmGithubService, private CrmGithubApiErrorResponseFactory $errorResponseFactory)
    {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/repositories/{repository}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Project $project, string $repository): JsonResponse
    {
        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse(
            $this->crmGithubService->getRepository($project, $repository),
        ), $this->errorResponseFactory);
    }
}
