<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\TaskParentRelationGuard;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_numeric;
use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchGeneralSubTaskController
{
    use GeneralCrudApiTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private TaskParentRelationGuard $taskParentRelationGuard,
    ) {
    }

    #[Route('/v1/crm/general/subtasks/{subtask}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        summary: 'General - Update Subtask',
        parameters: [new OA\Parameter(name: 'subtask', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['status' => 'in_progress', 'parentTaskId' => 'uuid'])),
        responses: [
            new OA\Response(response: 200, description: 'Sous-task mise à jour', content: new OA\JsonContent(example: ['id' => 'uuid'])),
            new OA\Response(response: 422, description: 'La task parente n est pas dans le même projet ou la relation est circulaire.'),
        ]
    )]
    public function __invoke(Task $subtask, Request $request): JsonResponse
    {
        if ($subtask->getParentTask() === null) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Provided task is not a subtask.');
        }

        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (isset($payload['title']) && is_string($payload['title']) && $payload['title'] !== '') {
            $subtask->setTitle($payload['title']);
        }

        if (isset($payload['description'])) {
            $subtask->setDescription($this->nullableString($payload['description']));
        }

        if (isset($payload['status'])) {
            $subtask->setStatus(TaskStatus::tryFrom((string) $payload['status']) ?? TaskStatus::TODO);
        }

        if (isset($payload['priority'])) {
            $subtask->setPriority(TaskPriority::tryFrom((string) $payload['priority']) ?? TaskPriority::MEDIUM);
        }

        if (isset($payload['dueAt'])) {
            $subtask->setDueAt($this->parseNullableDate($payload['dueAt']));
        }

        if (isset($payload['estimatedHours']) && is_numeric($payload['estimatedHours'])) {
            $subtask->setEstimatedHours((float) $payload['estimatedHours']);
        }

        if (array_key_exists('parentTaskId', $payload)) {
            $this->assignParentTask($subtask, $payload['parentTaskId']);
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $subtask->getId()]);
    }

    private function assignParentTask(Task $subtask, mixed $parentTaskId): void
    {
        if ($parentTaskId === null || $parentTaskId === '') {
            $subtask->setParentTask(null);

            return;
        }

        if (!is_string($parentTaskId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "parentTaskId" must be a UUID string or null.');
        }

        $parentTask = $this->taskRepository->find($parentTaskId);
        if ($parentTask === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Parent task not found.');
        }

        $this->taskParentRelationGuard->assertCanAssignParent($subtask, $parentTask, 'Provided "parentTaskId" must belong to the same project.');
        $subtask->setParentTask($parentTask);
    }
}
