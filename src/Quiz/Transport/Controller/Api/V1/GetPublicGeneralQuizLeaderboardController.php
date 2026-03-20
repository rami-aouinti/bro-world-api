<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\Quiz\Application\Service\QuizReadService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Quiz')]
final readonly class GetPublicGeneralQuizLeaderboardController
{
    #[Route('/v1/public/quiz/general/leaderboard', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Get top 3 users for general quiz weighted score',
        security: [],
        tags: ['Quiz'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Top users ranked by weighted average score.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'userId', type: 'string', example: '0195d47a-a42d-78d4-a9f9-01c6de1f3b43'),
                                new OA\Property(property: 'username', type: 'string', example: 'john-user'),
                                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                                new OA\Property(property: 'lastName', type: 'string', example: 'User'),
                                new OA\Property(property: 'attemptCount', type: 'integer', example: 4),
                                new OA\Property(property: 'averageWeightedScore', type: 'number', format: 'float', example: 156.24),
                            ]
                        )),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(QuizReadService $quizReadService): JsonResponse
    {
        return new JsonResponse([
            'items' => $quizReadService->getGeneralTopScores(3),
        ]);
    }
}
