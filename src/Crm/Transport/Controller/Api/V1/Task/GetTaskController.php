<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
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
final readonly class GetTaskController
{
    public function __construct(private TaskRepository $taskRepository, private CrmApplicationScopeResolver $scopeResolver, private CrmApiErrorResponseFactory $errorResponseFactory, private CrmApiNormalizer $normalizer) {}

    #[Route('/v1/crm/applications/{applicationSlug}/tasks/{id}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, string $id): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $task = $this->taskRepository->findOneScopedById($id, $crm->getId());
        if (!$task instanceof Task) { return $this->errorResponseFactory->notFoundReference('taskId'); }

        return new JsonResponse($this->normalizer->normalizeTask($task));
    }
}
