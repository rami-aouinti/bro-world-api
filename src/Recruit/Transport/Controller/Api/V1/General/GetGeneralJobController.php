<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Service\JobPublicDetailService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Recruit General Job')]
final readonly class GetGeneralJobController
{
    public function __construct(
        private JobPublicDetailService $jobPublicDetailService,
    ) {
    }

    #[OA\Get(
        summary: 'Détail public général d\'un job publié avec jobs similaires indexés.',
        security: [],
        parameters: [
            new OA\Parameter(name: 'jobSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail du job récupéré.'),
            new OA\Response(response: 400, description: 'Paramètre invalide.'),
            new OA\Response(response: 401, description: 'Authentification requise.'),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Job introuvable.'),
        ],
    )]
    public function __invoke(string $jobSlug): JsonResponse
    {
        return new JsonResponse($this->jobPublicDetailService->getGeneralDetail($jobSlug));
    }
}
