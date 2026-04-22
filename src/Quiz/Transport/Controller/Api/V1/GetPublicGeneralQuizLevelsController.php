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
final readonly class GetPublicGeneralQuizLevelsController
{
    #[OA\Get(summary: 'Get quiz levels (public)', security: [], tags: ['Quiz'])]
    public function __invoke(QuizReadService $quizReadService): JsonResponse
    {
        return new JsonResponse([
            'items' => $quizReadService->getLevels(),
        ]);
    }
}
