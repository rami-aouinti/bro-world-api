<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Application\Service\ResumeAiParsingService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class ResumeReviewController
{
    public function __construct(
        private ResumeAiParsingService $resumeAiParsingService,
    ) {
    }

    #[Route(path: '/v1/recruit/resumes/review', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Analyse un payload CV et retourne une review textuelle avec améliorations possibles.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['resumeData'],
            properties: [
                new OA\Property(
                    property: 'resumeData',
                    description: 'Données du CV à analyser (informations personnelles, expériences, formations, skills, etc.).',
                    type: 'object',
                    additionalProperties: true,
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Review textuelle retournée par l\'IA.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'review', type: 'string'),
            ],
            type: 'object',
            example: [
                'review' => 'needs improvement\nMissing quantified achievements in experiences.\n- Add metrics for each role.\n- Clarify education dates.',
            ],
        ),
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $resumeData = $payload['resumeData'] ?? null;

        if (!is_array($resumeData) || $resumeData === []) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "resumeData" is required and must be a non-empty object.');
        }

        return new JsonResponse([
            'review' => $this->resumeAiParsingService->reviewResume($resumeData),
        ]);
    }
}
