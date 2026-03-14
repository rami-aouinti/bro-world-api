<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Transport\Request\CreateTaskRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\General\Application\Message\EntityCreated;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateTaskController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private SprintRepository $sprintRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/tasks', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(summary: 'POST /v1/crm/applications/{applicationSlug}/tasks')]

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
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = CreateTaskRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $dueAt = $this->parseDate($input->dueAt, 'dueAt');
        if ($dueAt instanceof JsonResponse) {
            return $dueAt;
        }

        $task = new Task();
        $task->setTitle((string)$input->title)
            ->setDescription($input->description)
            ->setStatus(TaskStatus::tryFrom((string)$input->status) ?? TaskStatus::TODO)
            ->setPriority(TaskPriority::tryFrom((string)$input->priority) ?? TaskPriority::MEDIUM)
            ->setDueAt($dueAt)
            ->setEstimatedHours($input->estimatedHours !== null ? (float)$input->estimatedHours : null);

        $project = null;
        if (is_string($input->projectId)) {
            $project = $this->projectRepository->findOneScopedById($input->projectId, $crm->getId());
            if ($project === null) {
                return $this->errorResponseFactory->notFoundReference('projectId');
            }

            $task->setProject($project);
        }

        if (is_string($input->sprintId)) {
            $sprint = $this->sprintRepository->findOneScopedById($input->sprintId, $crm->getId());
            if ($sprint === null) {
                return $this->errorResponseFactory->notFoundReference('sprintId');
            }

            if ($project !== null && $sprint->getProject()?->getId() !== $project->getId()) {
                return $this->errorResponseFactory->outOfScopeReference('Provided "sprintId" does not belong to the provided "projectId".');
            }

            $task->setSprint($sprint);
        }

        if (is_array($input->assigneeIds)) {
            foreach ($input->assigneeIds as $assigneeId) {
                $assignee = $this->entityManager->getRepository(User::class)->find($assigneeId);
                if (!$assignee instanceof User) {
                    return $this->errorResponseFactory->notFoundReference('assigneeIds');
                }

                $task->addAssignee($assignee);
            }
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_task', $task->getId(), context: [
            'applicationSlug' => $applicationSlug,
            'crmId' => $crm->getId(),
        ]));

        return new JsonResponse([
            'id' => $task->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    private function parseDate(?string $value, string $field): DateTimeImmutable|JsonResponse|null
    {
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        if ($date === false) {
            return $this->errorResponseFactory->invalidDate($field);
        }

        return $date;
    }
}
