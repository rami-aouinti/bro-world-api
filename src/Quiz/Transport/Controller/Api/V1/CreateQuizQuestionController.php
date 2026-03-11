<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\Quiz\Application\Message\CreateQuizQuestionCommand;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
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
#[OA\Tag(name: 'Quiz')]
final class CreateQuizQuestionController
{
    #[Route('/v1/quiz/applications/{applicationSlug}/questions', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/quiz/applications/{applicationSlug}/questions', tags: ['Quiz'])]
    public function __invoke(string $applicationSlug, Request $request, MessageBusInterface $messageBus): JsonResponse
    {
        $user = $request->getUser();
        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_UNAUTHORIZED, 'User required.');
        }

        $payload = (array)json_decode((string)$request->getContent(), true);
        $messageBus->dispatch(new CreateQuizQuestionCommand(
            (string)uniqid('op_', true),
            $user->getId(),
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
