<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CreateTaskRequestEntryRequest;
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
final readonly class CreateTaskRequestController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
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
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = CreateTaskRequestEntryRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $resolvedAt = $this->parseDate($input->resolvedAt, 'resolvedAt');
        if ($resolvedAt instanceof JsonResponse) {
            return $resolvedAt;
        }

        $taskRequest = new TaskRequest();
        $taskRequest->setTitle((string)$input->title)
            ->setDescription($input->description)
            ->setStatus(TaskRequestStatus::tryFrom((string)$input->status) ?? TaskRequestStatus::PENDING)
            ->setResolvedAt($resolvedAt);

        if (is_string($input->taskId)) {
            $task = $this->taskRepository->findOneScopedById($input->taskId, $crm->getId());
            if ($task === null) {
                return $this->errorResponseFactory->notFoundReference('taskId');
            }

            $taskRequest->setTask($task);
        }

        if (is_array($input->assigneeIds)) {
            foreach ($input->assigneeIds as $assigneeId) {
                $assignee = $this->entityManager->getRepository(User::class)->find($assigneeId);
                if (!$assignee instanceof User) {
                    return $this->errorResponseFactory->notFoundReference('assigneeIds');
                }

                $taskRequest->addAssignee($assignee);
            }
        }

        $this->entityManager->persist($taskRequest);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_task_request', $taskRequest->getId(), context: ['applicationSlug' => $applicationSlug]));

        return new JsonResponse([
            'id' => $taskRequest->getId(),
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
