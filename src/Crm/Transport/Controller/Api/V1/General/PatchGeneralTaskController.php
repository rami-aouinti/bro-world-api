<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_numeric;
use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchGeneralTaskController
{
    use GeneralCrudApiTrait;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/v1/crm/general/tasks/{task}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'General - Update Task', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['status' => 'in_progress', 'estimatedHours' => 4.5])), responses: [new OA\Response(response: 200, description: 'Task mise à jour', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
    public function __invoke(Task $task, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (isset($payload['title']) && is_string($payload['title']) && $payload['title'] !== '') {
            $task->setTitle($payload['title']);
        }

        if (isset($payload['description'])) {
            $task->setDescription($this->nullableString($payload['description']));
        }

        if (isset($payload['status'])) {
            $task->setStatus(TaskStatus::tryFrom((string) $payload['status']) ?? TaskStatus::TODO);
        }

        if (isset($payload['priority'])) {
            $task->setPriority(TaskPriority::tryFrom((string) $payload['priority']) ?? TaskPriority::MEDIUM);
        }

        if (isset($payload['dueAt'])) {
            $task->setDueAt($this->parseNullableDate($payload['dueAt']));
        }

        if (isset($payload['estimatedHours']) && is_numeric($payload['estimatedHours'])) {
            $task->setEstimatedHours((float) $payload['estimatedHours']);
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $task->getId()]);
    }
}
