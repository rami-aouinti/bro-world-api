<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\Quiz\Application\Service\QuizReadService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Quiz')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class GetPrivateGeneralQuizController
{
    private const GENERAL_APPLICATION_SLUG = 'general';

    #[Route('/v1/private/quiz/general', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Get general quiz (private)', tags: ['Quiz'])]
    public function __invoke(Request $request, QuizReadService $quizReadService): JsonResponse
    {
        return new JsonResponse($quizReadService->getByApplicationSlug(
            self::GENERAL_APPLICATION_SLUG,
            $request->query->get('level'),
            $request->query->get('category'),
        ));
    }
}
