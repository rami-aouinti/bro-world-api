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

use function is_string;
use function trim;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class ResumeStructureFromTextController
{
    public function __construct(
        private ResumeAiParsingService $resumeAiParsingService,
    ) {
    }

    #[Route(path: '/v1/recruit/resumes/structure-from-text', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Transforme un texte brut de CV en CV structuré complet via IA locale.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['resumeText'],
            example: [
                'resumeText' => 'string',
            ],
            properties: [
                new OA\Property(property: 'resumeText', type: 'string', description: 'Texte brut contenant un CV.'),
            ],
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'CV structuré généré depuis le texte.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object'),
            ],
            type: 'object',
        ),
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $resumeText = $payload['resumeText'] ?? null;

        if (!is_string($resumeText) || trim($resumeText) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "resumeText" is required and must be a non-empty string.');
        }

        return new JsonResponse([
            'data' => $this->resumeAiParsingService->structureResumeFromText($resumeText),
        ]);
    }
}
