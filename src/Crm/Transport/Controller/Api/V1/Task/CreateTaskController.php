<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\General\Application\Message\EntityCreated;
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
final readonly class CreateTaskController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private SprintRepository $sprintRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/tasks', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/crm/tasks')]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = (array) json_decode((string) $request->getContent(), true);
        $task = new Task();
        $task->setTitle((string) ($payload['title'] ?? ''))
            ->setDescription(isset($payload['description']) ? (string) $payload['description'] : null)
            ->setStatus(TaskStatus::tryFrom((string) ($payload['status'] ?? '')) ?? TaskStatus::TODO)
            ->setPriority(TaskPriority::tryFrom((string) ($payload['priority'] ?? '')) ?? TaskPriority::MEDIUM)
            ->setDueAt(isset($payload['dueAt']) ? new DateTimeImmutable((string) $payload['dueAt']) : null)
            ->setEstimatedHours(isset($payload['estimatedHours']) ? (float) $payload['estimatedHours'] : null);
        if (is_string($payload['projectId'] ?? null)) {
            $task->setProject($this->projectRepository->find($payload['projectId']));
        }
        if (is_string($payload['sprintId'] ?? null)) {
            $task->setSprint($this->sprintRepository->find($payload['sprintId']));
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_task', $task->getId()));

        return new JsonResponse(['id' => $task->getId()], JsonResponse::HTTP_CREATED);
    }
}
