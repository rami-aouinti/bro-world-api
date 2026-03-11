<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Domain\Entity\Sprint;
use App\Crm\Infrastructure\Repository\SprintRepository;
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
final readonly class ListSprintsController
{
    public function __construct(
        private SprintRepository $sprintRepository
    ) {
    }

    #[Route('/v1/crm/{applicationSlug}/sprints', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        $items = array_map(static fn (Sprint $sprint): array => [
            'id' => $sprint->getId(),
            'name' => $sprint->getName(),
            'projectId' => $sprint->getProject()?->getId(),
            'status' => $sprint->getStatus()->value,
            'startDate' => $sprint->getStartDate()?->format('Y-m-d'),
            'endDate' => $sprint->getEndDate()?->format('Y-m-d'),
        ], $this->sprintRepository->findBy([], [
            'createdAt' => 'DESC',
        ], 200));

        return new JsonResponse([
            'items' => $items,
        ]);
    }
}
