<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\Quiz\Application\Message\CreateQuizQuestionCommand;
use App\Quiz\Application\Service\QuizReadService;
use App\User\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class QuizController
{
    public function __construct(private MessageBusInterface $messageBus, private QuizReadService $quizReadService) {}

    #[Route('/v1/quiz/application/{applicationSlug}', methods: [Request::METHOD_GET])]
    public function getByApplication(string $applicationSlug): JsonResponse
    {
        return new JsonResponse($this->quizReadService->getByApplicationSlug($applicationSlug));
    }

    #[Route('/v1/quiz/application/{applicationSlug}/questions', methods: [Request::METHOD_POST])]
    public function createQuestion(string $applicationSlug, Request $request): JsonResponse
    {
        $user = $request->getUser();
        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_UNAUTHORIZED, 'User required.');
        }
        $payload = (array) json_decode((string) $request->getContent(), true);
        $this->messageBus->dispatch(new CreateQuizQuestionCommand(
            (string) uniqid('op_', true),
            $user->getId(),
            $applicationSlug,
            (string) ($payload['title'] ?? ''),
            (string) ($payload['level'] ?? 'easy'),
            (string) ($payload['category'] ?? 'general'),
            (array) ($payload['answers'] ?? []),
            is_array($payload['configuration'] ?? null) ? $payload['configuration'] : null,
        ));

        return new JsonResponse(['status' => 'accepted'], JsonResponse::HTTP_ACCEPTED);
    }
}
