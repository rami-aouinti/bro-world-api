<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\TaskRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final class GetGeneralTaskRequestController
{
    use GeneralCrudApiTrait;

    #[Route('/v1/crm/general/task-requests/{taskRequest}', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'General - Get Task Request', responses: [new OA\Response(response: 200, description: 'Détail task request', content: new OA\JsonContent(example: ['id' => 'uuid', 'title' => 'Créer PR', 'status' => 'pending']))])]
    public function __invoke(TaskRequest $taskRequest): JsonResponse
    {
        return new JsonResponse($this->serializeTaskRequest($taskRequest));
    }
}
