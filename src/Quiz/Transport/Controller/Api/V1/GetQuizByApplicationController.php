<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\Quiz\Application\Service\QuizReadService;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Quiz')]
final class GetQuizByApplicationController
{
    /**
     * @throws InvalidArgumentException
     */
    #[Route('/v1/quiz/applications/{applicationSlug}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Request $request, QuizReadService $quizReadService): JsonResponse
    {
        return new JsonResponse($quizReadService->getByApplicationSlug(
            $applicationSlug,
            $request->query->get('level'),
            $request->query->get('category'),
        ));
    }
}
