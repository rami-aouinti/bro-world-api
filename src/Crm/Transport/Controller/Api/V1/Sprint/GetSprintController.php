<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Domain\Entity\Sprint;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class GetSprintController
{
    #[Route('/v1/crm/applications/{applicationSlug}/sprints/{sprint}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Sprint $sprint): JsonResponse
    {
        $assignee = $sprint->getAssignees()->toArray();

        foreach ($assignee as $key => $value) {
            $assignee[$key] = $value->toArray();
        }

        return new JsonResponse([
            'id' => $sprint->getId(),
            'project' => $sprint->getProject()->toArray(),
            'name' => $sprint->getName(),
            'goal' => $sprint->getGoal(),
            'status' => $sprint->getStatus()->value,
            'startDate' => $sprint->getStartDate()?->format('Y-m-d'),
            'endDate' => $sprint->getEndDate()?->format('Y-m-d'),
            'assignee' => $assignee,
        ]);
    }
}
