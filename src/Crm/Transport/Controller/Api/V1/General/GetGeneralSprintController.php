<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

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
final readonly class GetGeneralSprintController
{
    public function __construct()
    {
    }

    #[Route('/v1/crm/general/sprints/{sprint}', methods: [Request::METHOD_GET])]
    public function __invoke(Sprint $sprint): JsonResponse
    {
        return new JsonResponse([
            'id' => $sprint->getId(),
            'name' => $sprint->getName(),
            'status' => $sprint->getStatus()->value,
            'startDate' => $sprint->getStartDate()?->format(DATE_ATOM),
            'endDate' => $sprint->getEndDate()?->format(DATE_ATOM),
            'projectId' => $sprint->getProject()?->getId(),
        ]);
    }
}
