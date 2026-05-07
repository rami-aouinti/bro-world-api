<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\CoverLetter;

use App\Recruit\Application\Service\ResumeAiParsingService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Cover AI')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class AiCoverWritingController
{
    public function __construct(
        private ResumeAiParsingService $resumeAiParsingService,
    ) {
    }

    #[Route(path: '/v1/recruit/cover-pages/about-me/generate', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Génère un texte About Me pour cover page à partir d\'un profil utilisateur ou d\'une description de poste.')]
    public function generateAboutMe(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $inputText = isset($payload['text']) ? trim((string) $payload['text']) : '';

        if ($inputText === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "text" is required and must be a non-empty string.');
        }

        return new JsonResponse([
            'textArea' => $this->resumeAiParsingService->generateAboutMeForCoverPage($inputText),
        ]);
    }

    #[Route(path: '/v1/recruit/cover-letters/generate', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Détecte la company et ses besoins depuis un texte, puis génère un cover letter adapté.')]
    public function generateCoverLetter(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $inputText = isset($payload['text']) ? trim((string) $payload['text']) : '';

        if ($inputText === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "text" is required and must be a non-empty string.');
        }

        return new JsonResponse([
            'textArea' => $this->resumeAiParsingService->generateCoverLetterFromJobText($inputText),
        ]);
    }
}
