<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\Quiz\Application\Service\QuizEditorAccessService;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use App\Quiz\Application\Message\CreateQuizQuestionCommand;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Quiz')]
final class CreateQuizQuestionController
{
    /**
     * @throws ExceptionInterface
     * @throws JsonException
     */
    #[Route('/v1/quiz/applications/{applicationSlug}/questions', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/quiz/applications/{applicationSlug}/questions', tags: ['Quiz'])]
    public function __invoke(string $applicationSlug, Request $request, MessageBusInterface $messageBus, User $loggedInUser, ApplicationRepository $applicationRepository, QuizRepository $quizRepository, QuizEditorAccessService $accessService): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $application = $applicationRepository->findOneBy(['slug' => $applicationSlug]);
        if (!$application instanceof Application) {
            return new JsonResponse(['message' => 'Application not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $quiz = $quizRepository->findOneByApplication($application);
        if ($quiz === null) {
            return new JsonResponse(['message' => 'Quiz not found for application.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $accessService->assertCanEdit($quiz, $loggedInUser);
        $messageBus->dispatch(new CreateQuizQuestionCommand(
            (string)uniqid('op_', true),
            $loggedInUser->getId(),
            $applicationSlug,
            (string)($payload['title'] ?? ''),
            (string)($payload['level'] ?? 'easy'),
            (string)($payload['category'] ?? 'general'),
            (array)($payload['answers'] ?? []),
            (int)($payload['points'] ?? 1),
            is_string($payload['explanation'] ?? null) ? $payload['explanation'] : null,
            is_array($payload['configuration'] ?? null) ? $payload['configuration'] : null,
        ));

        return new JsonResponse([
            'status' => 'accepted',
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
