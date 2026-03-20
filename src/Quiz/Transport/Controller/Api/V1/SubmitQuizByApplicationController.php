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
    private const string GENERAL_APPLICATION_SLUG = 'general';

    /**
     * @throws JsonException
     */
    #[Route('/v1/quiz/applications/{applicationSlug}/submit', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Submit quiz answers for an application', tags: ['Quiz'])]
    public function __invoke(string $applicationSlug, Request $request, QuizSubmissionService $quizSubmissionService, User $loggedInUser): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return new JsonResponse($quizSubmissionService->submitByApplicationSlug($applicationSlug, $payload, $loggedInUser));
    }

    /**
     * @throws JsonException
     */
    #[Route('/v1/quiz/general/submit', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Submit general quiz answers and compute score',
        description: 'Endpoint to submit answers for the general quiz. Use this payload in api/doc to test score calculation.',
        tags: ['Quiz'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['answers'],
                properties: [
                    new OA\Property(
                        property: 'answers',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            required: ['questionId', 'answerId'],
                            properties: [
                                new OA\Property(property: 'questionId', type: 'string', example: '0195d45f-01ab-7e8d-9ac5-7ef2f1925b18'),
                                new OA\Property(property: 'answerId', type: 'string', example: '0195d460-5f6a-7f8f-84a6-0fdc9cb15ec1'),
                            ]
                        )
                    ),
                ],
                example: [
                    'answers' => [
                        ['questionId' => '0195d45f-01ab-7e8d-9ac5-7ef2f1925b18', 'answerId' => '0195d460-5f6a-7f8f-84a6-0fdc9cb15ec1'],
                        ['questionId' => '0195d45f-01ab-7e8d-9ac5-7ef2f1925b19', 'answerId' => '0195d460-5f6a-7f8f-84a6-0fdc9cb15ec2'],
                    ],
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quiz submission result with score and correction details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'attemptId', type: 'string', example: '0195d47a-a42d-78d4-a9f9-01c6de1f3b43'),
                        new OA\Property(property: 'quizId', type: 'string', example: '0195d41b-2f80-778a-b58a-5b7b938e2d3e'),
                        new OA\Property(property: 'applicationSlug', type: 'string', example: 'general'),
                        new OA\Property(property: 'passScore', type: 'integer', example: 70),
                        new OA\Property(property: 'score', type: 'number', format: 'float', example: 83.33),
                        new OA\Property(property: 'passed', type: 'boolean', example: true),
                        new OA\Property(property: 'totalQuestions', type: 'integer', example: 12),
                        new OA\Property(property: 'answeredQuestions', type: 'integer', example: 10),
                        new OA\Property(property: 'correctAnswers', type: 'integer', example: 9),
                        new OA\Property(property: 'totalPoints', type: 'integer', example: 18),
                        new OA\Property(property: 'earnedPoints', type: 'integer', example: 15),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Invalid payload.'),
            new OA\Response(response: 404, description: 'General quiz not found or unpublished.'),
        ]
    )]
    public function submitGeneral(Request $request, QuizSubmissionService $quizSubmissionService, User $loggedInUser): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return new JsonResponse($quizSubmissionService->submitByApplicationSlug(self::GENERAL_APPLICATION_SLUG, $payload, $loggedInUser));
    }
}
