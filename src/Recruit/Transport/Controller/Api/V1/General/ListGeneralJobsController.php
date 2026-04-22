<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Service\JobPublicListService;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Recruit General Job')]
final readonly class ListGeneralJobsController
{
    public function __construct(
        private JobPublicListService $jobPublicListService,
    ) {
    }

    /**
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    #[OA\Get(
        summary: 'Liste publique générale des offres jobs publiées, paginée et filtrable.',
        security: [],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'location', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'workMode', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Onsite', 'Remote', 'Hybrid'])),
            new OA\Parameter(name: 'contractType', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['CDI', 'CDD', 'Freelance', 'Internship'])),
            new OA\Parameter(name: 'experienceLevel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Junior', 'Mid', 'Senior', 'Lead'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des jobs récupérée.'),
            new OA\Response(response: 400, description: 'Paramètres de filtre invalides.'),
            new OA\Response(response: 401, description: 'Authentification requise.'),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Ressource introuvable.'),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->jobPublicListService->getGeneralList($request));
    }
}
