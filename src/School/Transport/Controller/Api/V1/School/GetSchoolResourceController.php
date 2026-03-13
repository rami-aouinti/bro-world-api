<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\School;

use App\School\Application\Service\SchoolResourceViewService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class GetSchoolResourceController
{
    public function __construct(
        private SchoolResourceViewService $resourceViewService
    ) {
    }
    public function __invoke(string $applicationSlug, string $resource, string $id): JsonResponse
    {
        $entity = $this->resourceViewService->findOr404($resource, $id);

        return new JsonResponse($this->resourceViewService->map($entity));
    }
}
