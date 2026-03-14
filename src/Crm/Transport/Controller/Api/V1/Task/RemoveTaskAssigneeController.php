<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class RemoveTaskAssigneeController
{
    public function __construct(private TaskRepository $taskRepository, private CrmApplicationScopeResolver $scopeResolver, private CrmApiErrorResponseFactory $errorResponseFactory, private EntityManagerInterface $entityManager) {}

    #[Route('/v1/crm/applications/{applicationSlug}/tasks/{id}/assignees/{userId}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $applicationSlug, string $id, string $userId): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $task = $this->taskRepository->findOneScopedById($id, $crm->getId());
        if (!$task instanceof Task) { return $this->errorResponseFactory->notFoundReference('taskId'); }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user instanceof User) { return $this->errorResponseFactory->notFoundReference('userId'); }

        $task->removeAssignee($user);
        $this->taskRepository->save($task);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
