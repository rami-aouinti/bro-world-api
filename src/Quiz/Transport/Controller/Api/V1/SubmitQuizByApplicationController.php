<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\Quiz\Application\Service\QuizSubmissionService;
use App\User\Domain\Entity\User;
use JsonException;
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
final class SubmitQuizByApplicationController
{
    /**
     * @throws JsonException
     */
    #[Route('/v1/quiz/applications/{applicationSlug}/submit', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/quiz/applications/{applicationSlug}/submit', tags: ['Quiz'])]
    public function __invoke(string $applicationSlug, Request $request, QuizSubmissionService $quizSubmissionService, User $loggedInUser): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return new JsonResponse($quizSubmissionService->submitByApplicationSlug($applicationSlug, $payload, $loggedInUser));
    }
}
