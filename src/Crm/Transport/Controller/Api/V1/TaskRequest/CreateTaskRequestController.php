<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\General\Application\Message\EntityCreated;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateTaskRequestController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/task-requests', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(summary: 'POST /v1/crm/applications/{applicationSlug}/task-requests')]

    #[OA\RequestBody(required: false, content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'description', type: 'string', nullable: true),
            new OA\Property(property: 'assigneeIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true),
        ]
    ))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true);
        $taskRequest = new TaskRequest();
        $taskRequest->setTitle((string)($payload['title'] ?? ''))
            ->setDescription(isset($payload['description']) ? (string)$payload['description'] : null)
            ->setStatus(TaskRequestStatus::tryFrom((string)($payload['status'] ?? '')) ?? TaskRequestStatus::PENDING)
            ->setResolvedAt(isset($payload['resolvedAt']) ? new DateTimeImmutable((string)$payload['resolvedAt']) : null);
        if (is_string($payload['taskId'] ?? null)) {
            $taskRequest->setTask($this->taskRepository->find($payload['taskId']));
        }
        if (is_array($payload['assigneeIds'] ?? null)) {
            foreach ($payload['assigneeIds'] as $assigneeId) {
                if (!is_string($assigneeId) || $assigneeId === '') {
                    continue;
                }

                $assignee = $this->entityManager->getRepository(User::class)->find($assigneeId);
                if ($assignee instanceof User) {
                    $taskRequest->addAssignee($assignee);
                }
            }
        }

        $this->entityManager->persist($taskRequest);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_task_request', $taskRequest->getId()));

        return new JsonResponse([
            'id' => $taskRequest->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
