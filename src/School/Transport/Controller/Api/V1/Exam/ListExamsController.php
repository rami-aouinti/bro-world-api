<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Exam;

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
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListExamsController
{
    public function __construct(private ExamListService $examListService)
    {
    }

    #[Route('/v1/school/exams', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->examListService->getList($request));
    }
}
