<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
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
final readonly class ListTaskRequestsController
{
    public function __construct(
        private TaskRequestRepository $taskRequestRepository
    ) {
    }

    #[Route('/v1/crm/{applicationSlug}/task-requests', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        $items = array_map(static fn (TaskRequest $taskRequest): array => [
            'id' => $taskRequest->getId(),
            'title' => $taskRequest->getTitle(),
            'status' => $taskRequest->getStatus()->value,
            'requestedAt' => $taskRequest->getRequestedAt()->format(DATE_ATOM),
            'resolvedAt' => $taskRequest->getResolvedAt()?->format(DATE_ATOM),
            'taskId' => $taskRequest->getTask()?->getId(),
        ], $this->taskRequestRepository->findBy([], [
            'createdAt' => 'DESC',
        ], 200));

        return new JsonResponse([
            'items' => $items,
        ]);
    }
}
