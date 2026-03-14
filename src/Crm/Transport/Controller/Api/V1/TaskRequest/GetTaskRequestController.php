<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
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
final readonly class GetTaskRequestController
{
    public function __construct(private TaskRequestRepository $taskRequestRepository, private CrmApplicationScopeResolver $scopeResolver, private CrmApiErrorResponseFactory $errorResponseFactory, private CrmApiNormalizer $normalizer) {}

    #[Route('/v1/crm/applications/{applicationSlug}/task-requests/{id}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, string $id): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $taskRequest = $this->taskRequestRepository->findOneScopedById($id, $crm->getId());
        if (!$taskRequest instanceof TaskRequest) { return $this->errorResponseFactory->notFoundReference('taskRequestId'); }

        return new JsonResponse($this->normalizer->normalizeTaskRequest($taskRequest));
    }
}
