<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Domain\Entity\Task;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class GetTaskController
{
    public function __construct(
        private CrmApiNormalizer $normalizer
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/tasks/{task}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Task $task): JsonResponse
    {
        return new JsonResponse($this->normalizer->normalizeTask($task));
    }
}
