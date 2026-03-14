<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\TaskRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use App\Crm\Application\Security\CrmPermissions;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(CrmPermissions::VIEW)]
final readonly class ListTasksByApplicationAndSprintController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiNormalizer $crmApiNormalizer,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/sprints/{sprintId}/tasks', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, string $sprintId): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $tasks = $this->taskRepository->findScopedBySprint($crm->getId(), $sprintId);

        return new JsonResponse([
            'items' => array_map(fn ($task): array => $this->crmApiNormalizer->normalizeTask($task), $tasks),
        ]);
    }
}
