<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\TaskParentRelationGuard;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
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
final readonly class PatchGeneralTaskController
{
    use GeneralCrudApiTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private ProjectRepository $projectRepository,
        private TaskParentRelationGuard $taskParentRelationGuard,
    ) {}

    #[Route('/v1/crm/general/tasks/{task}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'General - Update Task', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['status' => 'in_progress', 'estimatedHours' => 4.5, 'parentTaskId' => 'uuid'])), responses: [new OA\Response(response: 200, description: 'Task mise à jour', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
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

        if (array_key_exists('projectId', $payload)) {
            $this->assignProject($task, $payload['projectId']);
        }

        if (array_key_exists('parentTaskId', $payload)) {
            $this->assignParentTask($task, $payload['parentTaskId']);
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $task->getId()]);
    }

    private function assignParentTask(Task $task, mixed $parentTaskId): void
    {
        if ($parentTaskId === null || $parentTaskId === '') {
            $task->setParentTask(null);

            return;
        }

        if (!is_string($parentTaskId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "parentTaskId" must be a UUID string or null.');
        }

        $parentTask = $this->taskRepository->find($parentTaskId);
        if ($parentTask === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Parent task not found.');
        }

        $this->taskParentRelationGuard->assertCanAssignParent($task, $parentTask, 'Provided "parentTaskId" must belong to the same project.');
        $task->setParentTask($parentTask);
    }

    private function assignProject(Task $task, mixed $projectId): void
    {
        if (!is_string($projectId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "projectId" must be a UUID string.');
        }

        $project = $this->projectRepository->find($projectId);
        if ($project === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Project not found.');
        }

        $task->setProject($project);

        $sprint = $task->getSprint();
        if ($sprint !== null && $sprint->getProject()?->getId() !== $project->getId()) {
            $task->setSprint(null);
        }
    }
}
