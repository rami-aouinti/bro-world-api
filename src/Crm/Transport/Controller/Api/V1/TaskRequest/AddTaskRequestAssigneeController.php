<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class AddTaskRequestAssigneeController
{
    public function __construct(
        private TaskRequestRepository $taskRequestRepository
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/task-requests/{taskRequest}/assignees/{user}', methods: [Request::METHOD_PUT])]
    public function __invoke(string $applicationSlug, TaskRequest $taskRequest, User $user): JsonResponse
    {
        $taskRequest->addAssignee($user);
        $this->taskRequestRepository->save($taskRequest);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
