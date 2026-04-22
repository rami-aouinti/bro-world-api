<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\General;

use App\School\Application\Service\SchoolResourceViewService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
final readonly class GetGeneralSchoolResourceController
{
    public function __construct(
        private SchoolResourceViewService $resourceViewService,
    ) {
    }

    #[Route('/v1/school/{resource}/{id}', requirements: [
        'resource' => 'classes|students|teachers|courses|exams|grades',
    ], methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Détail global d\'une ressource school (scope General en lecture seule)')]
    public function __invoke(string $resource, string $id): JsonResponse
    {
        $entity = $this->resourceViewService->findOr404($resource, $id);

        return new JsonResponse($this->resourceViewService->map($entity));
    }
}
