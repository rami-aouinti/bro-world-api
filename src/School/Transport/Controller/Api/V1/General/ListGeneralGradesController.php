<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\General;

use App\School\Application\Service\ListGradesService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
final readonly class ListGeneralGradesController
{
    public function __construct(
        private ListGradesService $listGradesService,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    #[Route('/v1/school/general/grades', defaults: ['applicationSlug' => 'general'], methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Lister globalement les notes school (scope General en lecture seule)')]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->listGradesService->list($request));
    }
}
