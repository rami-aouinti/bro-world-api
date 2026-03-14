<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
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
final readonly class RemoveTaskRequestAssigneeController
{
    public function __construct(private TaskRequestRepository $taskRequestRepository, private CrmApplicationScopeResolver $scopeResolver, private CrmApiErrorResponseFactory $errorResponseFactory, private EntityManagerInterface $entityManager) {}

    #[Route('/v1/crm/applications/{applicationSlug}/task-requests/{id}/assignees/{userId}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $applicationSlug, string $id, string $userId): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $taskRequest = $this->taskRequestRepository->findOneScopedById($id, $crm->getId());
        if (!$taskRequest instanceof TaskRequest) { return $this->errorResponseFactory->notFoundReference('taskRequestId'); }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user instanceof User) { return $this->errorResponseFactory->notFoundReference('userId'); }

        $taskRequest->removeAssignee($user);
        $this->taskRequestRepository->save($taskRequest);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
