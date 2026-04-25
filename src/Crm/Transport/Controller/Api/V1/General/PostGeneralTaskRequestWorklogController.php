<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\TaskRequestWorklogReadService;
use App\Crm\Domain\Entity\TaskRequestWorklog;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function is_numeric;
use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class PostGeneralTaskRequestWorklogController
{
    use GeneralCrudApiTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRequestRepository $taskRequestRepository,
        private EmployeeRepository $employeeRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
        private TaskRequestWorklogReadService $taskRequestWorklogReadService,
    ) {
    }

    #[Route('/v1/crm/general/task-requests/{id}/worklogs', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'General - Log work on a task request',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(example: ['employeeId' => 'uuid', 'hours' => 2.5, 'comment' => 'Worked on API endpoint'])
        ),
        responses: [
            new OA\Response(response: JsonResponse::HTTP_CREATED, description: 'Worklog created'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'User is not allowed to log time for this task request'),
        ]
    )]
    public function __invoke(string $id, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $employeeId = $payload['employeeId'] ?? null;
        $hours = $payload['hours'] ?? null;

        if (!is_string($employeeId) || !is_numeric($hours) || (float) $hours <= 0.0) {
            return $this->badRequest('Fields "employeeId" (uuid) and "hours" (> 0) are required.');
        }

        $taskRequest = $this->taskRequestRepository->find($id);
        if ($taskRequest === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Task request not found.');
        }

        $crmId = $taskRequest->getTask()?->getProject()?->getCompany()?->getCrm()?->getId();
        if (!is_string($crmId) || $crmId === '') {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Task request has no CRM scope.');
        }

        $employee = $this->employeeRepository->findOneScopedById($employeeId, $crmId);
        if ($employee === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Employee not found in crm-general-core scope.');
        }

        $isPrivileged = $this->authorizationChecker->isGranted(Role::ROOT->value)
            || $this->authorizationChecker->isGranted(Role::CRM_OWNER->value);

        $isAssigned = $taskRequest->getAssignees()->exists(
            static fn (int $key, User $assignee): bool => $assignee->getId() === $loggedInUser->getId(),
        );

        if (!$isPrivileged && !$isAssigned) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You are not allowed to log work on this task request.');
        }

        if (!$isPrivileged && $employee->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You can only log work with your own employee profile.');
        }

        $worklog = (new TaskRequestWorklog())
            ->setTaskRequest($taskRequest)
            ->setEmployee($employee)
            ->setHours((float) $hours)
            ->setComment($this->nullableString($payload['comment'] ?? null))
            ->setLoggedByUser($loggedInUser);

        $taskRequest->addWorklog($worklog);

        $this->entityManager->persist($worklog);
        $this->entityManager->flush();

        $consumedHours = $this->taskRequestWorklogReadService->getConsumedHours($taskRequest->getId());

        return new JsonResponse([
            'id' => $worklog->getId(),
            'taskRequestId' => $taskRequest->getId(),
            'employeeId' => $employee->getId(),
            'loggedByUserId' => $loggedInUser->getId(),
            'plannedHours' => $taskRequest->getPlannedHours(),
            'consumedHours' => $consumedHours,
            'remainingHours' => $this->taskRequestWorklogReadService->getRemainingHours($taskRequest->getPlannedHours(), $consumedHours),
        ], JsonResponse::HTTP_CREATED);
    }
}
