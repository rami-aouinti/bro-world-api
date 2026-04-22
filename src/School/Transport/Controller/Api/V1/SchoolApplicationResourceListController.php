<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1;

use App\General\Application\Service\ApplicationScopeResolver;
use App\School\Application\Serializer\SchoolApiResponseSerializer;
use App\School\Application\Service\SchoolApplicationResourceListService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
final readonly class SchoolApplicationResourceListController
{
    public function __construct(
        private ApplicationScopeResolver $applicationScopeResolver,
        private SchoolApplicationScopeResolver $scopeResolver,
        private SchoolApplicationResourceListService $resourceListService,
        private SchoolApiResponseSerializer $responseSerializer,
    ) {
    }

    #[Route('/v1/school/{resource}', requirements: [
        'resource' => 'students|teachers|exams|grades',
    ], methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Retourne une collection d\'items selon la ressource demandée (students, teachers, exams, grades) dans le scope de l\'application.',
        summary: 'Lister les ressources school par application',
        parameters: [
            new OA\Parameter(name: 'resource', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['students', 'teachers', 'exams', 'grades'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1), description: 'Pagination (si supportée par la ressource).'),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20), description: 'Nombre d\'éléments par page (si supporté).'),
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtre texte libre (si supporté).'),
            new OA\Parameter(name: 'title', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtre de titre (notamment pour exams).'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des ressources retournée.',
                content: new OA\JsonContent(
                    example: [
                        'items' => [
                            [
                                'id' => '4cfada53-2cf2-49a7-a4fb-4a9682c3a0c0',
                                'name' => 'Alice Martin',
                                'classId' => '7600e750-f92f-4f9f-883a-26404b538f66',
                            ],
                        ],
                        'meta' => [
                            'pagination' => [
                                'page' => 1,
                                'limit' => 20,
                                'totalItems' => 1,
                                'totalPages' => 1,
                            ],
                            'filters' => [
                                'q' => 'alice',
                            ],
                            'applicationSlug' => 'school-crm',
                        ],
                    ],
                ),
            ),
            new OA\Response(response: 403, description: 'Accès refusé (authentification requise).'),
            new OA\Response(response: 404, description: 'Application ou ressource introuvable dans le scope.'),
            new OA\Response(response: 422, description: 'Paramètres de pagination/filtres invalides.'),
        ],
    )]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(Request $request, string $resource, ?User $loggedInUser): JsonResponse
    {
        $applicationSlug = $this->applicationScopeResolver->resolveFromRequest($request);
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);
        $items = $this->resourceListService->listByResource($resource, $school->getId());

        return new JsonResponse($this->responseSerializer->list($items, null, [
            'applicationSlug' => $applicationSlug,
            'schoolId' => $school->getId(),
        ]));
    }
}
