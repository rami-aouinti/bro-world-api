<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_map;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class ListGeneralTaskRequestsController
{
    use GeneralCrudApiTrait;

    public function __construct(private TaskRequestRepository $taskRequestRepository)
    {
    }

    #[Route('/v1/crm/general/task-requests', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'General - List Task Requests', responses: [new OA\Response(response: 200, description: 'Liste des task requests', content: new OA\JsonContent(example: ['items' => [['id' => 'uuid', 'title' => 'Créer PR']]]))])]
    public function __invoke(): JsonResponse
    {
        $items = array_map(fn (TaskRequest $taskRequest): array => $this->serializeTaskRequest($taskRequest), $this->taskRequestRepository->findBy([], ['createdAt' => 'DESC']));

        return new JsonResponse(['items' => $items]);
    }
}
