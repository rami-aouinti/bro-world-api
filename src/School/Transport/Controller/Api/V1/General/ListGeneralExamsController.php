<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\General;

use App\School\Application\Service\ExamListService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
final readonly class ListGeneralExamsController
{
    public function __construct(
        private ExamListService $examListService,
    ) {
    }

    #[Route('/v1/school/general/exams', methods: [Request::METHOD_GET], defaults: ['applicationSlug' => 'general'])]
    #[OA\Get(summary: 'Lister globalement les examens school (scope General en lecture seule)')]
    public function __invoke(Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', 'general');

        return new JsonResponse($this->examListService->list($request));
    }
}
