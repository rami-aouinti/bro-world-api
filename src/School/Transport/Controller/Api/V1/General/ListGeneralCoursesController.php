<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\General;

use App\School\Application\Service\ListCoursesService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
final readonly class ListGeneralCoursesController
{
    public function __construct(
        private ListCoursesService $listCoursesService,
    ) {
    }

    #[OA\Get(summary: 'Lister globalement les cours school (scope General en lecture seule)')]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->listCoursesService->list($request));
    }
}
