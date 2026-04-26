<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\General\Application\Service\ApplicationScopeResolver;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Infrastructure\Repository\QuizAttemptRepository;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Quiz')]
final readonly class GetQuizAttemptsByApplicationController
{
    public function __construct(
        private ApplicationScopeResolver $applicationScopeResolver,
        private QuizRepository $quizRepository,
        private QuizAttemptRepository $attemptRepository,
    ) {
    }

    #[Route('/v1/quiz/applications/{applicationSlug}/attempts', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'List current user quiz attempts by application', tags: ['Quiz'])]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $applicationSlug = $this->applicationScopeResolver->resolveFromRequest($request);
        $quiz = $this->quizRepository->findOneByApplicationSlugWithConfiguration($applicationSlug);
        if (!$quiz instanceof Quiz) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Quiz not found for this application.');
        }

        $attempts = $this->attemptRepository->findRecentByQuizAndUser($quiz, $loggedInUser);

        return new JsonResponse([
            'items' => array_map(static fn ($attempt): array => [
                'id' => $attempt->getId(),
                'score' => $attempt->getScore(),
                'passed' => $attempt->isPassed(),
                'totalQuestions' => $attempt->getTotalQuestions(),
                'correctAnswers' => $attempt->getCorrectAnswers(),
                'createdAt' => $attempt->getCreatedAt()?->format(DATE_ATOM),
            ], $attempts),
        ]);
    }
}
