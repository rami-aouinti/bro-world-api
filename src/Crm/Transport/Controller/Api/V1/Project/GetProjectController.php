<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class GetProjectController
{
    public function __construct(private ProjectRepository $projectRepository, private CrmApplicationScopeResolver $scopeResolver, private CrmApiErrorResponseFactory $errorResponseFactory) {}

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{id}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, string $id): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $project = $this->projectRepository->findOneScopedById($id, $crm->getId());
        if (!$project instanceof Project) { return $this->errorResponseFactory->notFoundReference('projectId'); }

        return new JsonResponse([
            'id' => $project->getId(),
            'companyId' => $project->getCompany()?->getId(),
            'name' => $project->getName(),
            'code' => $project->getCode(),
            'description' => $project->getDescription(),
            'status' => $project->getStatus()->value,
            'startedAt' => $project->getStartedAt()?->format(DATE_ATOM),
            'dueAt' => $project->getDueAt()?->format(DATE_ATOM),
        ]);
    }
}
