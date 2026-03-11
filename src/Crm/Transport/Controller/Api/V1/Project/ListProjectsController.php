<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
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
final readonly class ListProjectsController
{
    public function __construct(private ProjectRepository $projectRepository)
    {
    }

    #[Route('/v1/crm/projects', methods: [Request::METHOD_GET])]
    public function __invoke(): JsonResponse
    {
        $items = array_map(static fn (Project $project): array => [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'companyId' => $project->getCompany()?->getId(),
            'status' => $project->getStatus()->value,
        ], $this->projectRepository->findBy([], ['createdAt' => 'DESC'], 200));

        return new JsonResponse(['items' => $items]);
    }
}
