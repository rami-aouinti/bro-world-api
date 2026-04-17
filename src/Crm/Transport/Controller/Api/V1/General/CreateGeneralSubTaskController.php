<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\TaskParentRelationGuard;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\SprintRepository;
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
final readonly class CreateGeneralSubTaskController
{
    use GeneralCrudApiTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SprintRepository $sprintRepository,
        private TaskParentRelationGuard $taskParentRelationGuard,
    ) {
    }

    #[Route('/v1/crm/general/tasks/{task}/subtasks', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'General - Create Subtask',
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['title' => 'Créer un script de migration', 'priority' => 'high'])),
        responses: [
            new OA\Response(response: 201, description: 'Sous-task créée', content: new OA\JsonContent(example: ['id' => 'uuid'])),
            new OA\Response(response: 422, description: 'La task parente n est pas dans le même projet ou la relation est circulaire.'),
        ]
    )]
    public function __invoke(Task $task, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $title = $payload['title'] ?? null;
        if (!is_string($title) || $title === '') {
            return $this->badRequest('Field "title" is required.');
        }

        $subTask = (new Task())
            ->setProject($task->getProject())
            ->setTitle($title)
            ->setDescription($this->nullableString($payload['description'] ?? null))
            ->setStatus(TaskStatus::tryFrom((string) ($payload['status'] ?? 'todo')) ?? TaskStatus::TODO)
            ->setPriority(TaskPriority::tryFrom((string) ($payload['priority'] ?? 'medium')) ?? TaskPriority::MEDIUM)
            ->setDueAt($this->parseNullableDate($payload['dueAt'] ?? null));
        $this->taskParentRelationGuard->assertCanAssignParent($subTask, $task, 'Provided parent task must belong to the same project.');
        $subTask->setParentTask($task);

        if (isset($payload['estimatedHours']) && is_numeric($payload['estimatedHours'])) {
            $subTask->setEstimatedHours((float) $payload['estimatedHours']);
        }

        if (isset($payload['sprintId']) && is_string($payload['sprintId'])) {
            $sprint = $this->sprintRepository->find($payload['sprintId']);
            if ($sprint !== null) {
                if ($sprint->getProject()?->getId() !== $task->getProject()?->getId()) {
                    throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Provided "sprintId" does not belong to the parent task project.');
                }

                $subTask->setSprint($sprint);
            }
        }

        $this->entityManager->persist($subTask);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $subTask->getId()], JsonResponse::HTTP_CREATED);
    }
}
