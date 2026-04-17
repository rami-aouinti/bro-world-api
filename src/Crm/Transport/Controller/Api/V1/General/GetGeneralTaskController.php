<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Domain\Entity\Task;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class GetGeneralTaskController
{
    public function __construct(private CrmApiNormalizer $normalizer)
    {
    }

    #[Route('/v1/crm/general/tasks/{task}', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'General - Get Task',
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Task détaillée', content: new OA\JsonContent(example: [
            'id' => 'uuid',
            'title' => 'Implémenter la migration',
            'projectId' => 'uuid',
            'parentTaskId' => null,
            'subTasks' => [['id' => 'uuid', 'title' => 'Créer migration', 'parentTaskId' => 'uuid']],
        ]))]
    )]
    public function __invoke(Task $task): JsonResponse
    {
        return new JsonResponse($this->normalizer->normalizeTask($task));
    }
}
