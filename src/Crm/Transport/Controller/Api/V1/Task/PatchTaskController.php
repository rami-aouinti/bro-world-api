<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use App\Crm\Application\Security\CrmPermissions;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(CrmPermissions::EDIT)]
final readonly class PatchTaskController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private SprintRepository $sprintRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/tasks/{id}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $applicationSlug, string $id, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $task = $this->taskRepository->findOneScopedById($id, $crm->getId());
        if (!$task instanceof Task) {
            return $this->errorResponseFactory->notFoundReference('taskId');
        }

        try { $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);} catch (JsonException) { return $this->errorResponseFactory->invalidJson(); }
        if (!is_array($payload)) { return $this->errorResponseFactory->invalidJson(); }

        if (isset($payload['title'])) { $task->setTitle((string) $payload['title']); }
        if (array_key_exists('description', $payload)) { $task->setDescription($payload['description'] !== null ? (string) $payload['description'] : null); }
        if (isset($payload['status']) && is_string($payload['status'])) { $status = TaskStatus::tryFrom($payload['status']); if ($status) { $task->setStatus($status); } }
        if (isset($payload['priority']) && is_string($payload['priority'])) { $priority = TaskPriority::tryFrom($payload['priority']); if ($priority) { $task->setPriority($priority); } }
        if (array_key_exists('dueAt', $payload)) { $task->setDueAt($this->parseDate($payload['dueAt'])); }
        if (array_key_exists('estimatedHours', $payload)) { $task->setEstimatedHours(is_numeric($payload['estimatedHours']) ? (float) $payload['estimatedHours'] : null); }
        if (array_key_exists('sprintId', $payload)) {
            if ($payload['sprintId'] === null || $payload['sprintId'] === '') {
                $task->setSprint(null);
            } elseif (is_string($payload['sprintId'])) {
                $sprint = $this->sprintRepository->findOneScopedById($payload['sprintId'], $crm->getId());
                if ($sprint !== null) { $task->setSprint($sprint); }
            }
        }

        $this->taskRepository->save($task);

        return new JsonResponse(['id' => $task->getId()]);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || !is_string($value)) { return null; }
        $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        return $parsed === false ? null : $parsed;
    }
}
