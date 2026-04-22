<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\CrmProjectRepositoryRepository;
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

use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class CreateGeneralTaskRequestController
{
    use GeneralCrudApiTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private CrmProjectRepositoryRepository $crmProjectRepositoryRepository,
    ) {
    }

    #[OA\Post(summary: 'General - Create Task Request', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['taskId' => 'uuid', 'repositoryId' => 'uuid', 'title' => 'Corriger bug API', 'status' => 'pending'])), responses: [new OA\Response(response: 201, description: 'Task request créée', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $taskId = $payload['taskId'] ?? null;
        $repositoryId = $payload['repositoryId'] ?? null;
        $title = $payload['title'] ?? null;

        if (!is_string($taskId) || !is_string($repositoryId) || !is_string($title) || $title === '') {
            return $this->badRequest('Fields "taskId", "repositoryId" and "title" are required.');
        }

        $task = $this->taskRepository->find($taskId);
        if ($task === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Task not found.');
        }

        $repository = $this->crmProjectRepositoryRepository->find($repositoryId);
        if ($repository === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Repository not found.');
        }

        $taskRequest = (new TaskRequest())
            ->setTask($task)
            ->setRepository($repository)
            ->setTitle($title)
            ->setDescription($this->nullableString($payload['description'] ?? null))
            ->setStatus(TaskRequestStatus::tryFrom((string) ($payload['status'] ?? 'pending')) ?? TaskRequestStatus::PENDING);

        if (isset($payload['requestedAt']) && is_string($payload['requestedAt'])) {
            $taskRequest->setRequestedAt($this->parseDate($payload['requestedAt']));
        }

        if (isset($payload['resolvedAt'])) {
            $taskRequest->setResolvedAt($this->parseNullableDate($payload['resolvedAt']));
        }

        $this->entityManager->persist($taskRequest);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $taskRequest->getId()], JsonResponse::HTTP_CREATED);
    }
}
