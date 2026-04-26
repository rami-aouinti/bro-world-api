<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\General\Application\Service\ApplicationScopeResolver;
use App\Quiz\Application\Service\QuizReadService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Quiz')]
final class GetQuizStatsByApplicationController
{
    public function __construct(
        private readonly ApplicationScopeResolver $applicationScopeResolver,
    ) {
    }

    #[Route('/v1/quiz/applications/{applicationSlug}/stats', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request, QuizReadService $quizReadService): JsonResponse
    {
        $applicationSlug = $this->applicationScopeResolver->resolveFromRequest($request);

        return new JsonResponse($quizReadService->getStatsByApplicationSlug($applicationSlug));
    }
}
