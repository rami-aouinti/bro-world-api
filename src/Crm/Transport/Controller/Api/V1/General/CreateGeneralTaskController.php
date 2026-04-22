<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\TaskParentRelationGuard;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\SprintRepository;
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
final readonly class CreateGeneralTaskController
{
    use GeneralCrudApiTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private SprintRepository $sprintRepository,
        private TaskRepository $taskRepository,
        private TaskParentRelationGuard $taskParentRelationGuard,
    ) {
    }

    #[OA\Post(summary: 'General - Create Task', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['projectId' => 'uuid', 'title' => 'Configurer CI', 'priority' => 'high', 'parentTaskId' => 'uuid'])), responses: [new OA\Response(response: 201, description: 'Task créée', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $projectId = $payload['projectId'] ?? null;
        $title = $payload['title'] ?? null;
        if (!is_string($projectId) || !is_string($title) || $title === '') {
            return $this->badRequest('Fields "projectId" and "title" are required.');
        }

        $project = $this->projectRepository->find($projectId);
        if ($project === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Project not found.');
        }

        $task = (new Task())
            ->setProject($project)
            ->setTitle($title)
            ->setDescription($this->nullableString($payload['description'] ?? null))
            ->setStatus(TaskStatus::tryFrom((string) ($payload['status'] ?? 'todo')) ?? TaskStatus::TODO)
            ->setPriority(TaskPriority::tryFrom((string) ($payload['priority'] ?? 'medium')) ?? TaskPriority::MEDIUM)
            ->setDueAt($this->parseNullableDate($payload['dueAt'] ?? null));

        if (isset($payload['estimatedHours']) && is_numeric($payload['estimatedHours'])) {
            $task->setEstimatedHours((float) $payload['estimatedHours']);
        }

        if (isset($payload['sprintId']) && is_string($payload['sprintId'])) {
            $sprint = $this->sprintRepository->find($payload['sprintId']);
            if ($sprint !== null) {
                $task->setSprint($sprint);
            }
        }

        if (isset($payload['parentTaskId'])) {
            if ($payload['parentTaskId'] === null || $payload['parentTaskId'] === '') {
                $task->setParentTask(null);
            } elseif (!is_string($payload['parentTaskId'])) {
                return $this->badRequest('Field "parentTaskId" must be a UUID string or null.');
            } else {
                $parentTask = $this->taskRepository->find($payload['parentTaskId']);
                if ($parentTask === null) {
                    throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Parent task not found.');
                }

                $this->taskParentRelationGuard->assertCanAssignParent($task, $parentTask, 'Provided "parentTaskId" does not belong to the provided "projectId".');
                $task->setParentTask($parentTask);
            }
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $task->getId()], JsonResponse::HTTP_CREATED);
    }
}
